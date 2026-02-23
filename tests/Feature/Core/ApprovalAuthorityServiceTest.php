<?php

use App\Core\Approvals\ApprovalAuthorityService;
use App\Core\Approvals\Models\ApprovalAuthorityProfile;
use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

function makeApprovalActor(array $permissionSlugs = []): array
{
    $owner = User::factory()->create();
    $actor = User::factory()->create();

    $companyName = 'Approval Co '.Str::upper(Str::random(4));
    $company = Company::create([
        'name' => $companyName,
        'slug' => Str::slug($companyName).'-'.Str::lower(Str::random(4)),
        'timezone' => config('app.timezone', 'UTC'),
        'owner_id' => $owner->id,
    ]);

    $role = Role::create([
        'name' => 'Approval Role '.Str::upper(Str::random(4)),
        'slug' => 'approval-role-'.Str::lower(Str::random(8)),
        'description' => 'Approval test role',
        'data_scope' => User::DATA_SCOPE_COMPANY,
        'company_id' => null,
    ]);

    if ($permissionSlugs !== []) {
        $permissionIds = collect($permissionSlugs)
            ->map(function (string $slug) {
                return Permission::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $slug, 'group' => 'approvals']
                )->id;
            })
            ->all();

        $role->permissions()->sync($permissionIds);
    }

    $company->users()->attach($actor->id, [
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $actor->forceFill([
        'current_company_id' => $company->id,
    ])->save();

    return [$company, $actor, $role];
}

test('sod denies purchase final approval when requester and approver are the same user', function () {
    [$company, $actor] = makeApprovalActor();

    $service = app(ApprovalAuthorityService::class);

    $allowed = $service->canApprove(
        company: $company,
        approver: $actor,
        module: 'purchasing',
        action: 'po_final_approval',
        context: [
            'requested_by_user_id' => $actor->id,
        ]
    );

    expect($allowed)->toBeFalse();
});

test('approval authority enforces amount threshold from role profile', function () {
    [$company, $actor, $role] = makeApprovalActor();

    ApprovalAuthorityProfile::create([
        'company_id' => $company->id,
        'role_id' => $role->id,
        'module' => 'purchasing',
        'action' => 'po_final_approval',
        'max_amount' => 1000,
        'currency_code' => 'USD',
        'max_risk_level' => 'high',
        'requires_separate_requester' => true,
        'is_active' => true,
    ]);

    $service = app(ApprovalAuthorityService::class);
    $requester = User::factory()->create();

    $allowed = $service->canApprove(
        company: $company,
        approver: $actor,
        module: 'purchasing',
        action: 'po_final_approval',
        context: [
            'requested_by_user_id' => $requester->id,
            'amount' => 1500,
            'risk_level' => 'medium',
        ]
    );

    expect($allowed)->toBeFalse();
});

test('approval authority allows valid role profile approval', function () {
    [$company, $actor, $role] = makeApprovalActor();

    ApprovalAuthorityProfile::create([
        'company_id' => $company->id,
        'role_id' => $role->id,
        'module' => 'purchasing',
        'action' => 'po_final_approval',
        'max_amount' => 2500,
        'currency_code' => 'USD',
        'max_risk_level' => 'high',
        'requires_separate_requester' => true,
        'is_active' => true,
    ]);

    $service = app(ApprovalAuthorityService::class);
    $requester = User::factory()->create();

    $allowed = $service->canApprove(
        company: $company,
        approver: $actor,
        module: 'purchasing',
        action: 'po_final_approval',
        context: [
            'requested_by_user_id' => $requester->id,
            'amount' => 1250,
            'risk_level' => 'medium',
        ]
    );

    expect($allowed)->toBeTrue();
});

test('period close approvals require accounting close permission', function () {
    [$company, $actor, $role] = makeApprovalActor();

    $service = app(ApprovalAuthorityService::class);

    $denied = $service->canApprove(
        company: $company,
        approver: $actor,
        module: 'accounting',
        action: 'period_close'
    );

    expect($denied)->toBeFalse();

    $permission = Permission::firstOrCreate(
        ['slug' => 'accounting.period.close'],
        ['name' => 'Close Accounting Period', 'group' => 'accounting']
    );

    $role->permissions()->syncWithoutDetaching([$permission->id]);

    $allowed = $service->canApprove(
        company: $company,
        approver: $actor,
        module: 'accounting',
        action: 'period_close'
    );

    expect($allowed)->toBeTrue();
});
