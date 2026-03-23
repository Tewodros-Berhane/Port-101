<?php

use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function makeCompanyUserForModulePermissions(array $permissionSlugs = []): User
{
    $owner = User::factory()->create();
    $user = User::factory()->create();

    $companyName = 'Module Co '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => config('app.timezone', 'UTC'),
        'owner_id' => $owner->id,
    ]);

    $role = Role::create([
        'name' => 'Module Role '.Str::upper(Str::random(4)),
        'slug' => 'module-role-'.Str::lower(Str::random(8)),
        'description' => 'Module permission test role',
        'data_scope' => User::DATA_SCOPE_COMPANY,
        'company_id' => null,
    ]);

    if ($permissionSlugs !== []) {
        $permissionIds = collect($permissionSlugs)
            ->map(function (string $slug) {
                return Permission::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $slug, 'group' => 'modules']
                )->id;
            })
            ->all();

        $role->permissions()->sync($permissionIds);
    }

    $company->users()->attach($user->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill([
        'current_company_id' => $company->id,
    ])->save();

    return $user;
}

dataset('companyModuleAccessRoutes', [
    ['company.modules.sales', 'sales.leads.view'],
    ['company.modules.inventory', 'inventory.stock.view'],
    ['company.modules.projects', 'projects.projects.view'],
    ['company.modules.purchasing', 'purchasing.rfq.view'],
    ['company.modules.accounting', 'accounting.invoices.view'],
    ['company.modules.approvals', 'approvals.requests.view'],
    ['company.modules.reports', 'reports.view'],
    ['company.modules.integrations', 'integrations.webhooks.view'],
]);

test('company module routes require module permissions', function (
    string $routeName,
    string $permissionSlug
) {
    $user = makeCompanyUserForModulePermissions([]);

    actingAs($user)
        ->get(route($routeName))
        ->assertForbidden();
})->with('companyModuleAccessRoutes');

test('company module routes allow users with module permissions', function (
    string $routeName,
    string $permissionSlug
) {
    $user = makeCompanyUserForModulePermissions([$permissionSlug]);

    actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with('companyModuleAccessRoutes');
