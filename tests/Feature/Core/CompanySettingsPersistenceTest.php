<?php

use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\SettingsService;
use App\Models\User;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

function createCompanyUserForSettings(array $permissions): array
{
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $teammate = User::factory()->create();

    $companyName = 'Settings Co '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => 'UTC',
        'currency_code' => 'USD',
        'is_active' => true,
        'owner_id' => $owner->id,
    ]);

    $role = Role::create([
        'name' => 'Settings Role '.Str::upper(Str::random(4)),
        'slug' => 'settings-role-'.Str::lower(Str::random(8)),
        'description' => 'Settings test role',
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

    $company->users()->attach($actor->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $company->users()->attach($teammate->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $actor->forceFill(['current_company_id' => $company->id])->save();
    $teammate->forceFill(['current_company_id' => $company->id])->save();

    return [$actor, $teammate, $company];
}

test('company settings update persists scoped settings and notifies other users', function () {
    [$actor, $teammate, $company] = createCompanyUserForSettings([
        'core.settings.manage',
        'core.company.view',
    ]);

    actingAs($actor)
        ->put(route('company.settings.update'), [
            'name' => 'Updated Company Name',
            'timezone' => 'Africa/Lagos',
            'currency_code' => 'ngn',
            'fiscal_year_start' => '2026-01-01',
            'locale' => 'en_NG',
            'date_format' => 'd/m/Y',
            'number_format' => '1.234,56',
            'audit_retention_days' => 180,
        ])
        ->assertRedirect(route('company.settings.show'));

    $company->refresh();

    expect($company->name)->toBe('Updated Company Name');
    expect($company->timezone)->toBe('Africa/Lagos');
    expect($company->currency_code)->toBe('NGN');

    $settings = app(SettingsService::class)->getMany([
        'company.fiscal_year_start',
        'company.locale',
        'company.date_format',
        'company.number_format',
        'core.audit_logs.retention_days',
    ], $company->id);

    expect($settings['company.fiscal_year_start'])->toBe('2026-01-01');
    expect($settings['company.locale'])->toBe('en_NG');
    expect($settings['company.date_format'])->toBe('d/m/Y');
    expect($settings['company.number_format'])->toBe('1.234,56');
    expect((int) $settings['core.audit_logs.retention_days'])->toBe(180);

    $notification = $teammate->fresh()->notifications()->latest()->first();

    expect($notification)->not->toBeNull();
    expect($notification?->data['title'] ?? null)->toBe('Company settings updated');
});

