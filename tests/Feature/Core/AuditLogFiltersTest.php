<?php

use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

function createAuditUser(array $permissions): array
{
    $user = User::factory()->create();

    $companyName = 'Audit Co '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => config('app.timezone', 'UTC'),
        'owner_id' => $user->id,
    ]);

    $role = Role::create([
        'name' => 'Audit Role '.Str::upper(Str::random(4)),
        'slug' => 'audit-role-'.Str::lower(Str::random(8)),
        'description' => 'Audit role',
        'company_id' => null,
    ]);

    $permissionIds = collect($permissions)
        ->map(function (string $slug) {
            return Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'core']
            )->id;
        })
        ->all();

    $role->permissions()->sync($permissionIds);

    $company->users()->attach($user->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill([
        'current_company_id' => $company->id,
    ])->save();

    return [$user, $company];
}

function makeAuditLog(
    Company $company,
    ?User $actor,
    array $overrides = []
): AuditLog {
    return AuditLog::create(array_merge([
        'company_id' => $company->id,
        'user_id' => $actor?->id,
        'auditable_type' => Partner::class,
        'auditable_id' => (string) Str::uuid(),
        'action' => 'created',
        'changes' => ['after' => ['name' => 'Example']],
        'created_at' => now(),
    ], $overrides));
}

test('audit log export respects action filter', function () {
    [$user, $company] = createAuditUser([
        'core.audit_logs.view',
        'core.audit_logs.manage',
    ]);

    $actor = User::factory()->create(['name' => 'Actor One']);

    makeAuditLog($company, $actor, [
        'action' => 'created',
        'auditable_type' => Partner::class,
    ]);

    makeAuditLog($company, $actor, [
        'action' => 'updated',
        'auditable_type' => Partner::class,
    ]);

    actingAs($user)
        ->get(route('core.audit-logs.export', [
            'format' => 'json',
            'action' => 'created',
        ]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.action', 'created');
});

test('audit log export respects record type and actor filters', function () {
    [$user, $company] = createAuditUser([
        'core.audit_logs.view',
        'core.audit_logs.manage',
    ]);

    $actorOne = User::factory()->create(['name' => 'Actor One']);
    $actorTwo = User::factory()->create(['name' => 'Actor Two']);

    makeAuditLog($company, $actorOne, [
        'action' => 'created',
        'auditable_type' => Partner::class,
    ]);

    makeAuditLog($company, $actorTwo, [
        'action' => 'created',
        'auditable_type' => Product::class,
    ]);

    actingAs($user)
        ->get(route('core.audit-logs.export', [
            'format' => 'json',
            'record_type' => Partner::class,
            'actor_id' => $actorOne->id,
        ]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.record_type', class_basename(Partner::class))
        ->assertJsonPath('0.actor', 'Actor One');
});

test('audit log export respects date range filter', function () {
    [$user, $company] = createAuditUser([
        'core.audit_logs.view',
        'core.audit_logs.manage',
    ]);

    $actor = User::factory()->create(['name' => 'Actor One']);

    makeAuditLog($company, $actor, [
        'action' => 'created',
        'created_at' => Carbon::parse('2026-01-10 12:00:00'),
    ]);

    makeAuditLog($company, $actor, [
        'action' => 'updated',
        'created_at' => Carbon::parse('2026-02-15 08:00:00'),
    ]);

    actingAs($user)
        ->get(route('core.audit-logs.export', [
            'format' => 'json',
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
        ]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.action', 'updated');
});

test('audit log date filters validate format', function () {
    [$user] = createAuditUser([
        'core.audit_logs.view',
    ]);

    actingAs($user)
        ->from(route('core.audit-logs.index'))
        ->get(route('core.audit-logs.index', [
            'start_date' => '2026-99-99',
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors(['start_date']);
});
