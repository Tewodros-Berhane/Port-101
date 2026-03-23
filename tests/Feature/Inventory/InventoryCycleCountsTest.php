<?php

use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\SettingsService;
use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Inventory\InventorySetupService;
use App\Modules\Inventory\Models\InventoryCycleCount;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryLot;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function assignInventoryCountRole(User $user, string $companyId, array $permissionSlugs): void
{
    $role = Role::create([
        'name' => 'Inventory Count Role '.Str::upper(Str::random(4)),
        'slug' => 'inventory-count-role-'.Str::lower(Str::random(8)),
        'description' => 'Inventory cycle count test role',
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
        ->updateOrCreate([
            'company_id' => $companyId,
        ], [
            'role_id' => $role->id,
            'is_owner' => false,
        ]);

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

test('cycle counts create review and post stock adjustments for untracked products', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignInventoryCountRole($user, $company->id, [
        'inventory.stock.view',
        'inventory.moves.view',
        'inventory.moves.manage',
        'inventory.counts.view',
        'inventory.counts.manage',
    ]);

    app(InventorySetupService::class)->ensureDefaults($company->id, $user->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Cycle Count Widget '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'on_hand_quantity' => 10,
        'reserved_quantity' => 0,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('company.inventory.cycle-counts.store'), [
            'location_id' => $stockLocation->id,
            'product_ids' => [$product->id],
            'notes' => 'Quarterly spot count',
        ])
        ->assertRedirect();

    $cycleCount = InventoryCycleCount::query()->latest('created_at')->firstOrFail();

    actingAs($user)
        ->put(route('company.inventory.cycle-counts.update', $cycleCount), [
            'lines' => $cycleCount->lines->map(fn ($line) => [
                'id' => $line->id,
                'counted_quantity' => 8,
            ])->all(),
        ])
        ->assertSessionHas('success');

    actingAs($user)
        ->post(route('company.inventory.cycle-counts.review', $cycleCount))
        ->assertSessionHas('success');

    $cycleCount->refresh();

    expect($cycleCount->status)->toBe(InventoryCycleCount::STATUS_REVIEWED);
    expect($cycleCount->requires_approval)->toBeFalse();
    expect((float) $cycleCount->total_variance_quantity)->toBe(-2.0);
    expect((float) $cycleCount->total_absolute_variance_quantity)->toBe(2.0);

    actingAs($user)
        ->post(route('company.inventory.cycle-counts.post', $cycleCount))
        ->assertSessionHas('success');

    expect($cycleCount->fresh()?->status)->toBe(InventoryCycleCount::STATUS_POSTED);
    expect((float) InventoryStockLevel::query()
        ->where('location_id', $stockLocation->id)
        ->where('product_id', $product->id)
        ->value('on_hand_quantity'))->toBe(8.0);

    $adjustmentMove = InventoryStockMove::query()
        ->where('cycle_count_id', $cycleCount->id)
        ->where('move_type', InventoryStockMove::TYPE_ADJUSTMENT)
        ->first();

    expect($adjustmentMove)->not->toBeNull();
    expect($adjustmentMove?->status)->toBe(InventoryStockMove::STATUS_DONE);
    expect((string) $adjustmentMove?->source_location_id)->toBe((string) $stockLocation->id);
});

test('cycle count approvals sync into the approvals queue before posting', function () {
    [$inventoryUser, $company] = makeActiveCompanyMember();
    $approver = User::factory()->create();

    assignInventoryCountRole($inventoryUser, $company->id, [
        'inventory.stock.view',
        'inventory.moves.view',
        'inventory.moves.manage',
        'inventory.counts.view',
        'inventory.counts.manage',
    ]);

    assignInventoryCountRole($approver, $company->id, [
        'approvals.requests.view',
        'approvals.requests.manage',
    ]);

    app(InventorySetupService::class)->ensureDefaults($company->id, $inventoryUser->id);

    app(SettingsService::class)->set('company.approvals.enabled', true, $company->id, null, $inventoryUser->id);
    app(SettingsService::class)->set('company.approvals.policy', 'amount_based', $company->id, null, $inventoryUser->id);
    app(SettingsService::class)->set('company.inventory.cycle_count_approval_quantity_threshold', 1, $company->id, null, $inventoryUser->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Approval Count Widget '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_NONE,
        'is_active' => true,
        'created_by' => $inventoryUser->id,
        'updated_by' => $inventoryUser->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'on_hand_quantity' => 12,
        'reserved_quantity' => 0,
        'created_by' => $inventoryUser->id,
        'updated_by' => $inventoryUser->id,
    ]);

    actingAs($inventoryUser)
        ->post(route('company.inventory.cycle-counts.store'), [
            'location_id' => $stockLocation->id,
            'product_ids' => [$product->id],
        ])
        ->assertRedirect();

    $cycleCount = InventoryCycleCount::query()->latest('created_at')->firstOrFail();

    actingAs($inventoryUser)
        ->put(route('company.inventory.cycle-counts.update', $cycleCount), [
            'lines' => $cycleCount->lines->map(fn ($line) => [
                'id' => $line->id,
                'counted_quantity' => 9,
            ])->all(),
        ])
        ->assertSessionHas('success');

    actingAs($inventoryUser)
        ->post(route('company.inventory.cycle-counts.review', $cycleCount))
        ->assertSessionHas('success');

    $cycleCount->refresh();
    expect($cycleCount->approval_status)->toBe(InventoryCycleCount::APPROVAL_STATUS_PENDING);
    expect($cycleCount->requires_approval)->toBeTrue();

    $approvalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('source_type', InventoryCycleCount::class)
        ->where('source_id', $cycleCount->id)
        ->first();

    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($inventoryUser)
        ->post(route('company.inventory.cycle-counts.post', $cycleCount))
        ->assertForbidden();

    actingAs($approver)
        ->post(route('company.approvals.approve', $approvalRequest))
        ->assertSessionHas('success');

    expect($cycleCount->fresh()?->approval_status)->toBe(InventoryCycleCount::APPROVAL_STATUS_APPROVED);

    actingAs($inventoryUser)
        ->post(route('company.inventory.cycle-counts.post', $cycleCount))
        ->assertSessionHas('success');

    expect($cycleCount->fresh()?->status)->toBe(InventoryCycleCount::STATUS_POSTED);
});

test('lot tracked cycle counts post auditable lot adjustments', function () {
    [$user, $company] = makeActiveCompanyMember();

    assignInventoryCountRole($user, $company->id, [
        'inventory.stock.view',
        'inventory.moves.view',
        'inventory.moves.manage',
        'inventory.counts.view',
        'inventory.counts.manage',
    ]);

    app(InventorySetupService::class)->ensureDefaults($company->id, $user->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();

    $product = Product::create([
        'company_id' => $company->id,
        'name' => 'Tracked Count Widget '.Str::upper(Str::random(4)),
        'type' => Product::TYPE_STOCK,
        'tracking_mode' => Product::TRACKING_LOT,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'on_hand_quantity' => 6,
        'reserved_quantity' => 0,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $lot = InventoryLot::create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'location_id' => $stockLocation->id,
        'code' => 'LOT-COUNT-001',
        'tracking_mode' => Product::TRACKING_LOT,
        'quantity_on_hand' => 6,
        'quantity_reserved' => 0,
        'received_at' => now(),
        'last_moved_at' => now(),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    actingAs($user)
        ->post(route('company.inventory.cycle-counts.store'), [
            'location_id' => $stockLocation->id,
            'product_ids' => [$product->id],
        ])
        ->assertRedirect();

    $cycleCount = InventoryCycleCount::query()->latest('created_at')->firstOrFail();
    $line = $cycleCount->lines()->firstOrFail();

    expect((string) $line->lot_id)->toBe((string) $lot->id);

    actingAs($user)
        ->put(route('company.inventory.cycle-counts.update', $cycleCount), [
            'lines' => [
                ['id' => $line->id, 'counted_quantity' => 4],
            ],
        ])
        ->assertSessionHas('success');

    actingAs($user)
        ->post(route('company.inventory.cycle-counts.review', $cycleCount))
        ->assertSessionHas('success');

    actingAs($user)
        ->post(route('company.inventory.cycle-counts.post', $cycleCount))
        ->assertSessionHas('success');

    expect((float) $lot->fresh()?->quantity_on_hand)->toBe(4.0);
    expect((float) InventoryStockLevel::query()
        ->where('location_id', $stockLocation->id)
        ->where('product_id', $product->id)
        ->value('on_hand_quantity'))->toBe(4.0);

    $adjustmentMove = InventoryStockMove::query()
        ->where('cycle_count_id', $cycleCount->id)
        ->first();

    expect($adjustmentMove)->not->toBeNull();
    expect($adjustmentMove?->lines()->first()?->source_lot_id)->toBe($lot->id);
});
