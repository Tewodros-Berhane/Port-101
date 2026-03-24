<?php

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Inventory\InventorySetupService;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryReorderRule;
use App\Modules\Inventory\Models\InventoryReplenishmentSuggestion;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Purchasing\Models\PurchaseRfq;
use Database\Seeders\CoreRolesSeeder;

use function Pest\Laravel\actingAs;

function assignReorderingRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()
        ->where('slug', $roleSlug)
        ->whereNull('company_id')
        ->firstOrFail();

    $user->memberships()->updateOrCreate([
        'company_id' => $companyId,
    ], [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

test('reordering scan creates and refreshes replenishment suggestions without duplicates', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignReorderingRole($manager, $company->id, 'inventory_manager');

    app(InventorySetupService::class)->ensureDefaults($company->id, $manager->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Reorder Widget',
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'on_hand_quantity' => 3,
        'reserved_quantity' => 1,
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->post(route('company.inventory.reordering.store'), [
            'product_id' => $product->id,
            'location_id' => $stockLocation->id,
            'min_quantity' => 5,
            'max_quantity' => 10,
            'reorder_quantity' => 4,
            'is_active' => true,
        ])
        ->assertRedirect(route('company.inventory.reordering.index'));

    $rule = InventoryReorderRule::query()->firstOrFail();

    actingAs($manager)
        ->post(route('company.inventory.reordering.scan'))
        ->assertSessionHas('success');

    $suggestion = InventoryReplenishmentSuggestion::query()
        ->where('reorder_rule_id', $rule->id)
        ->where('status', InventoryReplenishmentSuggestion::STATUS_OPEN)
        ->first();

    expect($suggestion)->not->toBeNull();
    expect((float) $suggestion?->available_quantity)->toBe(2.0);
    expect((float) $suggestion?->projected_quantity)->toBe(2.0);
    expect((float) $suggestion?->suggested_quantity)->toBe(8.0);

    actingAs($manager)
        ->post(route('company.inventory.reordering.scan'))
        ->assertSessionHas('success');

    expect(InventoryReplenishmentSuggestion::query()
        ->where('reorder_rule_id', $rule->id)
        ->count())->toBe(1);

    InventoryStockLevel::query()
        ->where('product_id', $product->id)
        ->where('location_id', $stockLocation->id)
        ->update([
            'on_hand_quantity' => 12,
            'reserved_quantity' => 0,
            'updated_by' => $manager->id,
        ]);

    actingAs($manager)
        ->post(route('company.inventory.reordering.scan'))
        ->assertSessionHas('success');

    expect($suggestion?->fresh()->status)->toBe(InventoryReplenishmentSuggestion::STATUS_RESOLVED);
});

test('purchasing manager can convert an open replenishment suggestion into a draft rfq', function () {
    $this->seed(CoreRolesSeeder::class);

    [$inventoryManager, $company] = makeActiveCompanyMember();
    $purchasingManager = User::factory()->create();
    $company->users()->attach($purchasingManager->id, [
        'role_id' => null,
        'is_owner' => false,
    ]);

    assignReorderingRole($inventoryManager, $company->id, 'inventory_manager');
    assignReorderingRole($purchasingManager, $company->id, 'purchasing_manager');

    app(InventorySetupService::class)->ensureDefaults($company->id, $inventoryManager->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Purchasing Reorder Widget',
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $inventoryManager->id,
        'updated_by' => $inventoryManager->id,
    ]);

    $vendor = Partner::create([
        'company_id' => $company->id,
        'name' => 'Preferred Vendor',
        'type' => 'vendor',
        'is_active' => true,
        'created_by' => $inventoryManager->id,
        'updated_by' => $inventoryManager->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'on_hand_quantity' => 1,
        'reserved_quantity' => 0,
        'created_by' => $inventoryManager->id,
        'updated_by' => $inventoryManager->id,
    ]);

    InventoryReorderRule::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'location_id' => $stockLocation->id,
        'preferred_vendor_id' => $vendor->id,
        'min_quantity' => 5,
        'max_quantity' => 10,
        'reorder_quantity' => 4,
        'is_active' => true,
        'created_by' => $inventoryManager->id,
        'updated_by' => $inventoryManager->id,
    ]);

    actingAs($inventoryManager)
        ->post(route('company.inventory.reordering.scan'))
        ->assertSessionHas('success');

    $suggestion = InventoryReplenishmentSuggestion::query()
        ->where('status', InventoryReplenishmentSuggestion::STATUS_OPEN)
        ->firstOrFail();

    actingAs($purchasingManager)
        ->post(route('company.inventory.reordering.suggestions.convert', $suggestion), [])
        ->assertRedirect();

    $rfq = PurchaseRfq::query()->first();

    expect($rfq)->not->toBeNull();
    expect($rfq?->partner_id)->toBe($vendor->id);
    expect($rfq?->status)->toBe(PurchaseRfq::STATUS_DRAFT);
    expect((float) $rfq?->lines()->first()?->quantity)->toBe((float) $suggestion->suggested_quantity);
    expect($rfq?->lines()->first()?->product_id)->toBe($product->id);
    expect($suggestion->fresh()->status)->toBe(InventoryReplenishmentSuggestion::STATUS_CONVERTED);
    expect($suggestion->fresh()->rfq_id)->toBe($rfq?->id);
});

test('warehouse clerks can view reordering but cannot manage rules or scans', function () {
    $this->seed(CoreRolesSeeder::class);
    $this->withoutVite();

    [$user, $company] = makeActiveCompanyMember();
    assignReorderingRole($user, $company->id, 'warehouse_clerk');

    actingAs($user)
        ->get(route('company.inventory.reordering.index'))
        ->assertOk();

    actingAs($user)
        ->get(route('company.inventory.reordering.create'))
        ->assertForbidden();

    actingAs($user)
        ->post(route('company.inventory.reordering.scan'))
        ->assertForbidden();
});
