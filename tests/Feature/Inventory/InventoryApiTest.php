<?php

use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Inventory\InventorySetupService;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function assignInventoryApiRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Inventory API Role '.Str::upper(Str::random(4)),
        'slug' => 'inventory-api-role-'.Str::lower(Str::random(8)),
        'description' => 'Inventory API test role',
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

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

function createInventoryApiProduct(string $companyId, string $userId, string $name): Product
{
    return Product::create([
        'company_id' => $companyId,
        'name' => $name,
        'sku' => 'SKU-'.Str::upper(Str::random(6)),
        'type' => 'stock',
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('api v1 inventory endpoints are company scoped and support stock move workflows', function () {
    [$manager, $company] = makeActiveCompanyMember();
    [$otherManager, $otherCompany] = makeActiveCompanyMember();

    assignInventoryApiRole($manager, $company->id, [
        'inventory.stock.view',
        'inventory.moves.view',
        'inventory.moves.manage',
    ]);
    assignInventoryApiRole($otherManager, $otherCompany->id, [
        'inventory.stock.view',
        'inventory.moves.view',
        'inventory.moves.manage',
    ]);

    app(InventorySetupService::class)->ensureDefaults($company->id, $manager->id);
    app(InventorySetupService::class)->ensureDefaults($otherCompany->id, $otherManager->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();
    $customerLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'CUSTOMERS')
        ->firstOrFail();
    $vendorLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'VENDORS')
        ->firstOrFail();

    $otherStockLocation = InventoryLocation::query()
        ->where('company_id', $otherCompany->id)
        ->where('code', 'STOCK')
        ->firstOrFail();

    $product = createInventoryApiProduct($company->id, $manager->id, 'Inventory API Widget');
    $otherProduct = createInventoryApiProduct($otherCompany->id, $otherManager->id, 'Other API Widget');

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'on_hand_quantity' => 12,
        'reserved_quantity' => 0,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $otherCompany->id,
        'location_id' => $otherStockLocation->id,
        'product_id' => $otherProduct->id,
        'on_hand_quantity' => 9,
        'reserved_quantity' => 0,
        'created_by' => $otherManager->id,
        'updated_by' => $otherManager->id,
    ]);

    $otherMove = InventoryStockMove::create([
        'company_id' => $otherCompany->id,
        'reference' => 'OTH-MOVE-001',
        'move_type' => InventoryStockMove::TYPE_RECEIPT,
        'status' => InventoryStockMove::STATUS_DRAFT,
        'source_location_id' => null,
        'destination_location_id' => $otherStockLocation->id,
        'product_id' => $otherProduct->id,
        'quantity' => 4,
        'notes' => 'Out of scope move',
        'created_by' => $otherManager->id,
        'updated_by' => $otherManager->id,
    ]);

    Sanctum::actingAs($manager);

    getJson('/api/v1/inventory/stock-balances?search=Inventory%20API&sort=on_hand_quantity&direction=desc&per_page=500')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product_id', $product->id)
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonPath('meta.sort', 'on_hand_quantity')
        ->assertJsonPath('meta.direction', 'desc')
        ->assertJsonPath('meta.filters.search', 'Inventory API');

    $receiptResponse = postJson('/api/v1/inventory/stock-moves', [
        'reference' => 'RCV-API-001',
        'move_type' => InventoryStockMove::TYPE_RECEIPT,
        'source_location_id' => $vendorLocation->id,
        'destination_location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'notes' => 'API receipt',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', InventoryStockMove::STATUS_DRAFT)
        ->assertJsonPath('data.move_type', InventoryStockMove::TYPE_RECEIPT);

    $receiptMoveId = (string) $receiptResponse->json('data.id');

    postJson("/api/v1/inventory/stock-moves/{$receiptMoveId}/receive")
        ->assertOk()
        ->assertJsonPath('data.status', InventoryStockMove::STATUS_DONE)
        ->assertJsonPath('data.can_receive', false);

    $receiptLevel = InventoryStockLevel::query()
        ->where('company_id', $company->id)
        ->where('location_id', $stockLocation->id)
        ->where('product_id', $product->id)
        ->first();

    expect((float) $receiptLevel?->on_hand_quantity)->toBe(17.0);

    $deliveryResponse = postJson('/api/v1/inventory/stock-moves', [
        'reference' => 'DLV-API-001',
        'move_type' => InventoryStockMove::TYPE_DELIVERY,
        'source_location_id' => $stockLocation->id,
        'destination_location_id' => $customerLocation->id,
        'product_id' => $product->id,
        'quantity' => 4,
        'notes' => 'API delivery',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', InventoryStockMove::STATUS_DRAFT)
        ->assertJsonPath('data.can_dispatch', true);

    $deliveryMoveId = (string) $deliveryResponse->json('data.id');

    getJson('/api/v1/inventory/stock-moves?move_type=delivery&status=draft&sort=reference&direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $deliveryMoveId)
        ->assertJsonPath('meta.filters.move_type', InventoryStockMove::TYPE_DELIVERY)
        ->assertJsonPath('meta.filters.status', InventoryStockMove::STATUS_DRAFT)
        ->assertJsonPath('meta.sort', 'reference');

    getJson('/api/v1/inventory/stock-moves/'.$otherMove->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');

    postJson("/api/v1/inventory/stock-moves/{$deliveryMoveId}/reserve")
        ->assertOk()
        ->assertJsonPath('data.status', InventoryStockMove::STATUS_RESERVED)
        ->assertJsonPath('data.can_reserve', false);

    postJson("/api/v1/inventory/stock-moves/{$deliveryMoveId}/dispatch")
        ->assertOk()
        ->assertJsonPath('data.status', InventoryStockMove::STATUS_DONE)
        ->assertJsonPath('data.can_dispatch', false);

    $postDispatchLevel = InventoryStockLevel::query()
        ->where('company_id', $company->id)
        ->where('location_id', $stockLocation->id)
        ->where('product_id', $product->id)
        ->first();

    expect((float) $postDispatchLevel?->on_hand_quantity)->toBe(13.0);
    expect((float) $postDispatchLevel?->reserved_quantity)->toBe(0.0);

    $transferLocation = InventoryLocation::create([
        'company_id' => $company->id,
        'warehouse_id' => $stockLocation->warehouse_id,
        'code' => 'TRANS-'.Str::upper(Str::random(4)),
        'name' => 'Transfer Bay '.Str::upper(Str::random(3)),
        'type' => InventoryLocation::TYPE_INTERNAL,
        'is_active' => true,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $transferResponse = postJson('/api/v1/inventory/stock-moves', [
        'reference' => 'TRF-API-001',
        'move_type' => InventoryStockMove::TYPE_TRANSFER,
        'source_location_id' => $stockLocation->id,
        'destination_location_id' => $transferLocation->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'notes' => 'API transfer',
    ])
        ->assertCreated();

    $transferMoveId = (string) $transferResponse->json('data.id');

    patchJson('/api/v1/inventory/stock-moves/'.$transferMoveId, [
        'reference' => 'TRF-API-001A',
        'move_type' => InventoryStockMove::TYPE_TRANSFER,
        'source_location_id' => $stockLocation->id,
        'destination_location_id' => $transferLocation->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'notes' => 'Updated API transfer',
    ])
        ->assertOk()
        ->assertJsonPath('data.reference', 'TRF-API-001A')
        ->assertJsonPath('data.quantity', 3);

    postJson("/api/v1/inventory/stock-moves/{$transferMoveId}/reserve")
        ->assertOk()
        ->assertJsonPath('data.status', InventoryStockMove::STATUS_RESERVED);

    postJson("/api/v1/inventory/stock-moves/{$transferMoveId}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', InventoryStockMove::STATUS_DONE)
        ->assertJsonPath('data.can_complete', false);

    $draftResponse = postJson('/api/v1/inventory/stock-moves', [
        'reference' => 'DRF-API-001',
        'move_type' => InventoryStockMove::TYPE_RECEIPT,
        'source_location_id' => $vendorLocation->id,
        'destination_location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'notes' => 'Draft to delete',
    ])->assertCreated();

    $draftMoveId = (string) $draftResponse->json('data.id');

    deleteJson('/api/v1/inventory/stock-moves/'.$draftMoveId)
        ->assertNoContent();
});

test('api v1 inventory permissions and workflow errors use the shared contract', function () {
    [$viewer, $company] = makeActiveCompanyMember();

    assignInventoryApiRole($viewer, $company->id, [
        'inventory.stock.view',
        'inventory.moves.view',
    ]);

    app(InventorySetupService::class)->ensureDefaults($company->id, $viewer->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();
    $customerLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'CUSTOMERS')
        ->firstOrFail();
    $product = createInventoryApiProduct($company->id, $viewer->id, 'Inventory API Restricted Widget');

    $move = InventoryStockMove::create([
        'company_id' => $company->id,
        'reference' => 'LOCKED-API-001',
        'move_type' => InventoryStockMove::TYPE_DELIVERY,
        'status' => InventoryStockMove::STATUS_DRAFT,
        'source_location_id' => $stockLocation->id,
        'destination_location_id' => $customerLocation->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'notes' => 'Restricted API move',
        'created_by' => $viewer->id,
        'updated_by' => $viewer->id,
    ]);

    Sanctum::actingAs($viewer);

    getJson('/api/v1/inventory/stock-balances')
        ->assertOk();

    postJson('/api/v1/inventory/stock-moves', [
        'reference' => 'FORBIDDEN-API-001',
        'move_type' => InventoryStockMove::TYPE_RECEIPT,
        'source_location_id' => null,
        'destination_location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'notes' => 'Should fail',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    postJson('/api/v1/inventory/stock-moves/'.$move->id.'/dispatch')
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');

    assignInventoryApiRole($viewer, $company->id, [
        'inventory.stock.view',
        'inventory.moves.view',
        'inventory.moves.manage',
    ]);

    Sanctum::actingAs($viewer);

    postJson('/api/v1/inventory/stock-moves/'.$move->id.'/dispatch')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Delivery moves must be reserved before completion.');
});
