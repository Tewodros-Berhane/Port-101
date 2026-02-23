<?php

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;

function makeScopedCompany(): Company
{
    $owner = User::factory()->create();
    $companyName = 'Scope Co '.Str::upper(Str::random(4));

    return Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => config('app.timezone', 'UTC'),
        'owner_id' => $owner->id,
    ]);
}

function makePartnerRole(string $scope, string $suffix): Role
{
    $role = Role::create([
        'name' => 'Scoped Role '.$suffix,
        'slug' => 'scoped-role-'.Str::lower(Str::random(8)).'-'.$suffix,
        'description' => 'Data scope test role',
        'data_scope' => $scope,
        'company_id' => null,
    ]);

    $permissionIds = collect([
        Permission::firstOrCreate(
            ['slug' => 'core.partners.view'],
            ['name' => 'View Partners', 'group' => 'master_data']
        )->id,
        Permission::firstOrCreate(
            ['slug' => 'core.partners.manage'],
            ['name' => 'Manage Partners', 'group' => 'master_data']
        )->id,
    ])->all();

    $role->permissions()->sync($permissionIds);

    return $role;
}

function attachCompanyMember(Company $company, User $user, Role $role): void
{
    $company->users()->attach($user->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill([
        'current_company_id' => $company->id,
    ])->save();
}

function makeCompanyPartner(Company $company, User $creator, array $overrides = []): Partner
{
    return Partner::create(array_merge([
        'company_id' => $company->id,
        'code' => 'P-'.Str::upper(Str::random(6)),
        'name' => 'Partner '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'email' => 'partner.'.Str::lower(Str::random(5)).'@example.com',
        'phone' => '555-0100',
        'is_active' => true,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ], $overrides));
}

test('own-record data scope restricts partner updates to creator records', function () {
    $company = makeScopedCompany();
    $actor = User::factory()->create();
    $creator = User::factory()->create();

    $ownRole = makePartnerRole(User::DATA_SCOPE_OWN, 'own');

    attachCompanyMember($company, $actor, $ownRole);
    attachCompanyMember($company, $creator, $ownRole);

    $foreignPartner = makeCompanyPartner($company, $creator);
    $ownPartner = makeCompanyPartner($company, $actor);

    expect($actor->can('update', $foreignPartner))->toBeFalse();
    expect($actor->can('update', $ownPartner))->toBeTrue();
});

test('team-record data scope allows same-role records but blocks outside role records', function () {
    $company = makeScopedCompany();
    $actor = User::factory()->create();
    $teammate = User::factory()->create();
    $outsider = User::factory()->create();

    $teamRole = makePartnerRole(User::DATA_SCOPE_TEAM, 'team');
    $otherRole = makePartnerRole(User::DATA_SCOPE_OWN, 'other');

    attachCompanyMember($company, $actor, $teamRole);
    attachCompanyMember($company, $teammate, $teamRole);
    attachCompanyMember($company, $outsider, $otherRole);

    $teamPartner = makeCompanyPartner($company, $teammate);
    $outsidePartner = makeCompanyPartner($company, $outsider);

    expect($actor->can('update', $teamPartner))->toBeTrue();
    expect($actor->can('update', $outsidePartner))->toBeFalse();
});

test('data scope filters partner index lists for own and team records', function () {
    $company = makeScopedCompany();

    $ownActor = User::factory()->create();
    $ownOther = User::factory()->create();
    $ownRole = makePartnerRole(User::DATA_SCOPE_OWN, 'own-list');

    attachCompanyMember($company, $ownActor, $ownRole);
    attachCompanyMember($company, $ownOther, $ownRole);

    makeCompanyPartner($company, $ownActor, ['name' => 'Own Visible Partner']);
    makeCompanyPartner($company, $ownOther, ['name' => 'Own Hidden Partner']);

    actingAs($ownActor)
        ->get(route('core.partners.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('core/partners/index')
            ->has('partners.data', 1)
            ->where('partners.data.0.name', 'Own Visible Partner')
        );

    $teamActor = User::factory()->create();
    $teammate = User::factory()->create();
    $outsider = User::factory()->create();
    $teamRole = makePartnerRole(User::DATA_SCOPE_TEAM, 'team-list');
    $outsideRole = makePartnerRole(User::DATA_SCOPE_OWN, 'outside-list');

    attachCompanyMember($company, $teamActor, $teamRole);
    attachCompanyMember($company, $teammate, $teamRole);
    attachCompanyMember($company, $outsider, $outsideRole);

    makeCompanyPartner($company, $teamActor, ['name' => 'Team Actor Partner']);
    makeCompanyPartner($company, $teammate, ['name' => 'Team Mate Partner']);
    makeCompanyPartner($company, $outsider, ['name' => 'Outside Hidden Partner']);

    actingAs($teamActor)
        ->get(route('core.partners.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('core/partners/index')
            ->has('partners.data', 2)
        );
});
