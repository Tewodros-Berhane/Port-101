<?php

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Inventory\InventoryBundleService;
use App\Modules\Inventory\InventorySetupService;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

test('product bundle configuration persists through the product store flow', function () {
    $this->seed(CoreRolesSeeder::class);

    [, $company] = makeActiveCompanyMember();
    $user = User::query()->findOrFail($company->owner_id);
    $company->users()->syncWithoutDetaching([
        $user->id => [
            'role_id' => null,
            'is_owner' => true,
        ],
    ]);
    $user->forceFill(['current_company_id' => $company->id])->save();

    $componentA = Product::create([
        'company_id' => $company->id,
        'name' => 'Bundle Component A '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $componentB = Product::create([
        'company_id' => $company->id,
        'name' => 'Bundle Component B '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('core.products.store'), [
            'sku' => 'KIT-'.Str::upper(Str::random(6)),
            'name' => 'Configured Kit '.Str::upper(Str::random(4)),
            'type' => Product::TYPE_STOCK,
            'tracking_mode' => Product::TRACKING_NONE,
            'description' => 'Bundle-configured product',
            'is_active' => true,
            'bundle' => [
                'enabled' => true,
                'mode' => 'sales_only',
                'components' => [
                    [
                        'product_id' => $componentA->id,
                        'quantity' => 2,
                    ],
                    [
                        'product_id' => $componentB->id,
                        'quantity' => 1,
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $product = Product::query()
        ->with('bundle.components')
        ->where('company_id', $company->id)
        ->where('name', 'like', 'Configured Kit %')
        ->latest('created_at')
        ->firstOrFail();

    expect($product->bundle)->not->toBeNull();
    expect($product->bundle?->mode)->toBe('sales_only');
    expect($product->bundle?->components)->toHaveCount(2);
});

test('confirmed bundle sales orders reserve component demand and keep invoice lines on the sold bundle', function () {
    $this->seed(CoreRolesSeeder::class);

    [, $company] = makeActiveCompanyMember();
    $user = User::query()->findOrFail($company->owner_id);
    $company->users()->syncWithoutDetaching([
        $user->id => [
            'role_id' => null,
            'is_owner' => true,
        ],
    ]);
    $user->forceFill(['current_company_id' => $company->id])->save();

    app(InventorySetupService::class)->ensureDefaults($company->id, $user->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();

    $componentA = Product::create([
        'company_id' => $company->id,
        'name' => 'Fulfillment Component A '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $componentB = Product::create([
        'company_id' => $company->id,
        'name' => 'Fulfillment Component B '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $serviceProduct = Product::create([
        'company_id' => $company->id,
        'name' => 'Bundle Service Add-on '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_SERVICE,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $bundleProduct = Product::create([
        'company_id' => $company->id,
        'name' => 'Fulfillment Kit '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    app(InventoryBundleService::class)->syncProductBundle(
        product: $bundleProduct,
        payload: [
            'enabled' => true,
            'mode' => 'sales_only',
            'components' => [
                [
                    'product_id' => $componentA->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $componentB->id,
                    'quantity' => 1,
                ],
            ],
        ],
        actorId: $user->id,
    );

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $componentA->id,
        'on_hand_quantity' => 20,
        'reserved_quantity' => 0,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $componentB->id,
        'on_hand_quantity' => 20,
        'reserved_quantity' => 0,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $partner = Partner::create([
        'company_id' => $company->id,
        'name' => 'Bundle Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $order = SalesOrder::create([
        'company_id' => $company->id,
        'quote_id' => null,
        'partner_id' => $partner->id,
        'order_number' => 'SO-KIT-'.Str::upper(Str::random(4)),
        'status' => SalesOrder::STATUS_DRAFT,
        'order_date' => now()->toDateString(),
        'subtotal' => 900,
        'discount_total' => 0,
        'tax_total' => 0,
        'grand_total' => 900,
        'requires_approval' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    SalesOrderLine::create([
        'company_id' => $company->id,
        'order_id' => $order->id,
        'product_id' => $bundleProduct->id,
        'description' => 'Commercial bundle line',
        'quantity' => 3,
        'unit_price' => 250,
        'discount_percent' => 0,
        'tax_rate' => 0,
        'line_subtotal' => 750,
        'line_total' => 750,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    SalesOrderLine::create([
        'company_id' => $company->id,
        'order_id' => $order->id,
        'product_id' => $serviceProduct->id,
        'description' => 'Implementation service',
        'quantity' => 1,
        'unit_price' => 150,
        'discount_percent' => 0,
        'tax_rate' => 0,
        'line_subtotal' => 150,
        'line_total' => 150,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('company.sales.orders.confirm', $order))
        ->assertRedirect();

    $moves = InventoryStockMove::query()
        ->where('company_id', $company->id)
        ->where('related_sales_order_id', $order->id)
        ->where('move_type', InventoryStockMove::TYPE_DELIVERY)
        ->orderBy('product_id')
        ->get();

    expect($moves)->toHaveCount(2);
    expect($moves->pluck('product_id')->all())
        ->toEqualCanonicalizing([$componentA->id, $componentB->id]);
    expect((float) $moves->firstWhere('product_id', $componentA->id)?->quantity)->toBe(6.0);
    expect((float) $moves->firstWhere('product_id', $componentB->id)?->quantity)->toBe(3.0);
    expect($moves->every(fn (InventoryStockMove $move) => $move->status === InventoryStockMove::STATUS_RESERVED))->toBeTrue();

    expect(
        InventoryStockMove::query()
            ->where('company_id', $company->id)
            ->where('related_sales_order_id', $order->id)
            ->where('product_id', $bundleProduct->id)
            ->exists()
    )->toBeFalse();

    expect(
        InventoryStockMove::query()
            ->where('company_id', $company->id)
            ->where('related_sales_order_id', $order->id)
            ->where('product_id', $serviceProduct->id)
            ->exists()
    )->toBeFalse();

    expect((float) InventoryStockLevel::query()
        ->where('company_id', $company->id)
        ->where('location_id', $stockLocation->id)
        ->where('product_id', $componentA->id)
        ->value('reserved_quantity'))->toBe(6.0);
    expect((float) InventoryStockLevel::query()
        ->where('company_id', $company->id)
        ->where('location_id', $stockLocation->id)
        ->where('product_id', $componentB->id)
        ->value('reserved_quantity'))->toBe(3.0);

    $invoice = AccountingInvoice::query()
        ->where('company_id', $company->id)
        ->where('sales_order_id', $order->id)
        ->firstOrFail();

    expect($invoice->delivery_status)->toBe(AccountingInvoice::DELIVERY_STATUS_PENDING);
    expect($invoice->lines()->count())->toBe(2);
    expect($invoice->lines()->where('product_id', $bundleProduct->id)->exists())->toBeTrue();
    expect($invoice->lines()->where('product_id', $serviceProduct->id)->exists())->toBeTrue();
    expect($invoice->lines()->whereIn('product_id', [$componentA->id, $componentB->id])->exists())->toBeFalse();

    actingAs($user)
        ->post(route('company.inventory.moves.complete', $moves->firstWhere('product_id', $componentA->id)))
        ->assertRedirect();

    expect($invoice->fresh()->delivery_status)->toBe(AccountingInvoice::DELIVERY_STATUS_PENDING);
    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_CONFIRMED);

    actingAs($user)
        ->post(route('company.inventory.moves.complete', $moves->firstWhere('product_id', $componentB->id)))
        ->assertRedirect();

    expect($invoice->fresh()->delivery_status)->toBe(AccountingInvoice::DELIVERY_STATUS_READY);
    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_FULFILLED);
});
