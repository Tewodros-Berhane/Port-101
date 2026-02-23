<?php

use App\Modules\Inventory\InventorySetupService;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Inventory\Models\InventoryWarehouse;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignInventoryWorkflowRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Inventory Role '.Str::upper(Str::random(4)),
        'slug' => 'inventory-role-'.Str::lower(Str::random(8)),
        'description' => 'Inventory workflow test role',
        'data_scope' => User::DATA_SCOPE_COMPANY,
        'company_id' => null,
    ]);

    $permissionIds = collect($permissionSlugs)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'inventory']
            )->id;
        })
        ->all();

    $role->permissions()->sync($permissionIds);

    $user->memberships()
        ->where('company_id', $companyId)
        ->update([
            'role_id' => $role->id,
            'is_owner' => false,
        ]);
}

test('inventory move lifecycle updates stock levels consistently', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignInventoryWorkflowRole($user, $company->id, [
        'inventory.stock.view',
        'inventory.moves.view',
        'inventory.moves.manage',
    ]);

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Inventory Widget '.Str::upper(Str::random(4)),
        'type' => 'stock',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('company.inventory.warehouses.store'), [
            'code' => 'WH1',
            'name' => 'Warehouse 1',
            'is_active' => true,
        ])
        ->assertRedirect();

    $warehouse = InventoryWarehouse::query()->where('code', 'WH1')->first();
    expect($warehouse)->not->toBeNull();

    actingAs($user)
        ->post(route('company.inventory.locations.store'), [
            'warehouse_id' => $warehouse?->id,
            'code' => 'WH1-STOCK',
            'name' => 'WH1 Stock',
            'type' => InventoryLocation::TYPE_INTERNAL,
            'is_active' => true,
        ])
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.inventory.locations.store'), [
            'warehouse_id' => $warehouse?->id,
            'code' => 'WH1-OUT',
            'name' => 'WH1 Outbound',
            'type' => InventoryLocation::TYPE_INTERNAL,
            'is_active' => true,
        ])
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.inventory.locations.store'), [
            'warehouse_id' => null,
            'code' => 'CUSTOMERS-X',
            'name' => 'Customers X',
            'type' => InventoryLocation::TYPE_CUSTOMER,
            'is_active' => true,
        ])
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.inventory.locations.store'), [
            'warehouse_id' => null,
            'code' => 'VENDORS-X',
            'name' => 'Vendors X',
            'type' => InventoryLocation::TYPE_VENDOR,
            'is_active' => true,
        ])
        ->assertRedirect();

    $stockLocation = InventoryLocation::query()->where('code', 'WH1-STOCK')->first();
    $outboundLocation = InventoryLocation::query()->where('code', 'WH1-OUT')->first();
    $customerLocation = InventoryLocation::query()->where('code', 'CUSTOMERS-X')->first();
    $vendorLocation = InventoryLocation::query()->where('code', 'VENDORS-X')->first();

    actingAs($user)
        ->post(route('company.inventory.moves.store'), [
            'reference' => 'RCV-001',
            'move_type' => InventoryStockMove::TYPE_RECEIPT,
            'source_location_id' => $vendorLocation?->id,
            'destination_location_id' => $stockLocation?->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'related_sales_order_id' => null,
            'notes' => 'Initial receipt',
        ])
        ->assertRedirect();

    $receiptMove = InventoryStockMove::query()->where('reference', 'RCV-001')->first();

    actingAs($user)
        ->post(route('company.inventory.moves.complete', $receiptMove))
        ->assertRedirect();

    $stockLevel = InventoryStockLevel::query()
        ->where('location_id', $stockLocation?->id)
        ->where('product_id', $product->id)
        ->first();

    expect($stockLevel)->not->toBeNull();
    expect((float) $stockLevel?->on_hand_quantity)->toBe(10.0);
    expect((float) $stockLevel?->reserved_quantity)->toBe(0.0);

    actingAs($user)
        ->post(route('company.inventory.moves.store'), [
            'reference' => 'DLV-001',
            'move_type' => InventoryStockMove::TYPE_DELIVERY,
            'source_location_id' => $stockLocation?->id,
            'destination_location_id' => $customerLocation?->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'related_sales_order_id' => null,
            'notes' => 'Customer delivery',
        ])
        ->assertRedirect();

    $deliveryMove = InventoryStockMove::query()->where('reference', 'DLV-001')->first();

    actingAs($user)
        ->post(route('company.inventory.moves.reserve', $deliveryMove))
        ->assertRedirect();

    expect((float) $stockLevel?->fresh()?->reserved_quantity)->toBe(4.0);

    actingAs($user)
        ->post(route('company.inventory.moves.complete', $deliveryMove))
        ->assertRedirect();

    expect((float) $stockLevel?->fresh()?->on_hand_quantity)->toBe(6.0);
    expect((float) $stockLevel?->fresh()?->reserved_quantity)->toBe(0.0);

    actingAs($user)
        ->post(route('company.inventory.moves.store'), [
            'reference' => 'TRF-001',
            'move_type' => InventoryStockMove::TYPE_TRANSFER,
            'source_location_id' => $stockLocation?->id,
            'destination_location_id' => $outboundLocation?->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'related_sales_order_id' => null,
            'notes' => 'Rebalancing transfer',
        ])
        ->assertRedirect();

    $transferMove = InventoryStockMove::query()->where('reference', 'TRF-001')->first();

    actingAs($user)
        ->post(route('company.inventory.moves.reserve', $transferMove))
        ->assertRedirect();

    actingAs($user)
        ->post(route('company.inventory.moves.complete', $transferMove))
        ->assertRedirect();

    $sourceLevel = InventoryStockLevel::query()
        ->where('location_id', $stockLocation?->id)
        ->where('product_id', $product->id)
        ->first();

    $outboundLevel = InventoryStockLevel::query()
        ->where('location_id', $outboundLocation?->id)
        ->where('product_id', $product->id)
        ->first();

    expect((float) $sourceLevel?->on_hand_quantity)->toBe(4.0);
    expect((float) $sourceLevel?->reserved_quantity)->toBe(0.0);
    expect((float) $outboundLevel?->on_hand_quantity)->toBe(2.0);
    expect((float) $outboundLevel?->reserved_quantity)->toBe(0.0);
});

test('confirmed sales orders auto create reserved delivery moves that can be completed', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignInventoryWorkflowRole($user, $company->id, [
        'inventory.stock.view',
        'inventory.moves.view',
        'inventory.moves.manage',
        'sales.orders.view',
        'sales.orders.manage',
    ]);

    app(InventorySetupService::class)->ensureDefaults($company->id, $user->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->first();

    $customerLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'CUSTOMERS')
        ->first();

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Sales Reserved Widget '.Str::upper(Str::random(4)),
        'type' => 'stock',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Inventory Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation?->id,
        'product_id' => $product->id,
        'on_hand_quantity' => 10,
        'reserved_quantity' => 0,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $order = SalesOrder::create([
        'company_id' => $company->id,
        'quote_id' => null,
        'partner_id' => $partner->id,
        'order_number' => 'SO-TEST-001',
        'status' => SalesOrder::STATUS_DRAFT,
        'order_date' => now()->toDateString(),
        'subtotal' => 300,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 300,
        'requires_approval' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    SalesOrderLine::create([
        'company_id' => $company->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'description' => 'Reserved shipment line',
        'quantity' => 3,
        'unit_price' => 100,
        'discount_percent' => 0,
        'tax_rate' => 0,
        'line_subtotal' => 300,
        'line_total' => 300,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('company.sales.orders.confirm', $order))
        ->assertRedirect();

    $reservationMove = InventoryStockMove::query()
        ->where('company_id', $company->id)
        ->where('related_sales_order_id', $order->id)
        ->where('move_type', InventoryStockMove::TYPE_DELIVERY)
        ->latest('created_at')
        ->first();

    expect($reservationMove)->not->toBeNull();
    expect($reservationMove?->status)->toBe(InventoryStockMove::STATUS_RESERVED);
    expect((string) $reservationMove?->source_location_id)->toBe((string) $stockLocation?->id);
    expect((string) $reservationMove?->destination_location_id)->toBe((string) $customerLocation?->id);

    $stockLevel = InventoryStockLevel::query()
        ->where('company_id', $company->id)
        ->where('location_id', $stockLocation?->id)
        ->where('product_id', $product->id)
        ->first();

    expect((float) $stockLevel?->reserved_quantity)->toBe(3.0);
    expect((float) $stockLevel?->on_hand_quantity)->toBe(10.0);

    actingAs($user)
        ->post(route('company.inventory.moves.complete', $reservationMove))
        ->assertRedirect();

    expect($reservationMove?->fresh()->status)->toBe(InventoryStockMove::STATUS_DONE);
    expect((float) $stockLevel?->fresh()?->on_hand_quantity)->toBe(7.0);
    expect((float) $stockLevel?->fresh()?->reserved_quantity)->toBe(0.0);
});


