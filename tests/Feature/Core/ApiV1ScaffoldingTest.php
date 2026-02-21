<?php

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

function createCompanyUserForApi(array $permissions): array
{
    $owner = User::factory()->create();
    $user = User::factory()->create();

    $companyName = 'API Co '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);

    $role = Role::create([
        'name' => 'API Role '.Str::upper(Str::random(4)),
        'slug' => 'api-role-'.Str::lower(Str::random(8)),
        'description' => 'API test role',
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

    $user->forceFill(['current_company_id' => $company->id])->save();

    return [$user, $company];
}

test('api v1 health endpoint returns ok status', function () {
    getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('version', 'v1');
});

test('api v1 protected endpoints require token auth', function () {
    getJson('/api/v1/partners')
        ->assertUnauthorized();
});

test('api v1 accepts bearer personal access tokens', function () {
    [$user] = createCompanyUserForApi(['core.partners.view']);

    $token = $user->createToken('integration-test', ['*'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/partners')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

test('api v1 partners index is company scoped', function () {
    [$user, $company] = createCompanyUserForApi(['core.partners.view']);

    $otherOwner = User::factory()->create();
    $otherCompany = Company::create([
        'name' => 'Other API Company',
        'slug' => 'other-api-company-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'is_active' => true,
        'owner_id' => $otherOwner->id,
    ]);

    $inCompanyPartner = Partner::create([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'In Company Partner',
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    Partner::create([
        'company_id' => $otherCompany->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Out Company Partner',
        'type' => 'vendor',
        'is_active' => true,
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);

    Sanctum::actingAs($user);

    getJson('/api/v1/partners')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inCompanyPartner->id);
});

test('api v1 settings endpoint persists company settings', function () {
    [$user, $company] = createCompanyUserForApi([
        'core.company.view',
        'core.settings.manage',
    ]);

    Sanctum::actingAs($user);

    putJson('/api/v1/settings', [
        'locale' => 'en_US',
        'audit_retention_days' => 120,
        'tax_period' => 'monthly',
        'tax_submission_day' => 18,
        'approval_enabled' => true,
        'approval_policy' => 'amount_based',
        'approval_threshold_amount' => 9000.5,
        'sales_order_prefix' => 'SOA',
        'sales_order_next_number' => 1200,
    ])
        ->assertOk()
        ->assertJsonPath('data.locale', 'en_US')
        ->assertJsonPath('data.audit_retention_days', 120)
        ->assertJsonPath('data.tax_period', 'monthly')
        ->assertJsonPath('data.tax_submission_day', 18)
        ->assertJsonPath('data.approval_enabled', true)
        ->assertJsonPath('data.approval_policy', 'amount_based')
        ->assertJsonPath('data.approval_threshold_amount', 9000.5)
        ->assertJsonPath('data.sales_order_prefix', 'SOA')
        ->assertJsonPath('data.sales_order_next_number', 1200);

    getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonPath('data.locale', 'en_US')
        ->assertJsonPath('data.audit_retention_days', 120)
        ->assertJsonPath('data.tax_period', 'monthly')
        ->assertJsonPath('data.approval_enabled', true)
        ->assertJsonPath('data.sales_order_prefix', 'SOA');

    $setting = Setting::query()
        ->where('company_id', $company->id)
        ->where('key', 'core.audit_logs.retention_days')
        ->first();

    expect((int) $setting?->value)->toBe(120);

    $taxPeriod = Setting::query()
        ->where('company_id', $company->id)
        ->where('key', 'company.tax_period')
        ->first();

    expect($taxPeriod?->value)->toBe('monthly');
});
