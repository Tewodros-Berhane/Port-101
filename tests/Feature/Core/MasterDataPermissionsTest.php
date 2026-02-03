<?php

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Permission;
use App\Models\User;
use Illuminate\Support\Str;

test('super admin can view but cannot manage master data', function () {
    Permission::create([
        'name' => 'View Partners',
        'slug' => 'core.partners.view',
        'group' => 'master_data',
    ]);

    Permission::create([
        'name' => 'Manage Partners',
        'slug' => 'core.partners.manage',
        'group' => 'master_data',
    ]);

    $user = User::factory()->create([
        'is_super_admin' => true,
    ]);

    $partner = new Partner();

    expect($user->can('viewAny', Partner::class))->toBeTrue();
    expect($user->can('create', Partner::class))->toBeFalse();
    expect($user->can('update', $partner))->toBeFalse();
    expect($user->can('delete', $partner))->toBeFalse();
});

test('company owners bypass master data permissions', function () {
    Permission::create([
        'name' => 'View Partners',
        'slug' => 'core.partners.view',
        'group' => 'master_data',
    ]);

    Permission::create([
        'name' => 'Manage Partners',
        'slug' => 'core.partners.manage',
        'group' => 'master_data',
    ]);

    $user = User::factory()->create();

    $companyName = 'Owner Company';
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName),
        'owner_id' => $user->id,
    ]);

    $company->users()->attach($user->id, [
        'role_id' => null,
        'is_owner' => true,
    ]);

    $user->forceFill([
        'current_company_id' => $company->id,
    ])->save();

    $partner = new Partner();

    expect($user->can('viewAny', Partner::class))->toBeTrue();
    expect($user->can('create', Partner::class))->toBeTrue();
    expect($user->can('update', $partner))->toBeTrue();
    expect($user->can('delete', $partner))->toBeTrue();
});
