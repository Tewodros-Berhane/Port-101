<?php

use App\Core\Access\Models\Invite;
use App\Core\RBAC\Models\Role;
use App\Jobs\SendInviteLinkMail;
use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function assignHrEmployeeAccessRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()
        ->where('slug', $roleSlug)
        ->whereNull('company_id')
        ->firstOrFail();

    $membership = $user->memberships()->where('company_id', $companyId)->firstOrFail();

    $membership->update([
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

test('hr manager can create an employee without system access', function () {
    $this->seed(CoreRolesSeeder::class);
    Queue::fake();

    [$manager, $company] = makeActiveCompanyMember();
    assignHrEmployeeAccessRole($manager, $company->id, 'hr_manager');

    actingAs($manager)
        ->post(route('company.hr.employees.store'), [
            'user_id' => '',
            'requires_system_access' => false,
            'department_id' => '',
            'department_name' => 'Operations',
            'designation_id' => '',
            'designation_name' => 'Coordinator',
            'system_role_id' => '',
            'employee_number' => 'EMP-ACCESS-001',
            'employment_status' => HrEmployee::STATUS_ACTIVE,
            'employment_type' => HrEmployee::TYPE_FULL_TIME,
            'first_name' => 'Aster',
            'last_name' => 'NoAccess',
            'work_email' => 'aster.noaccess@example.test',
            'login_email' => '',
            'hire_date' => now()->toDateString(),
            'timezone' => 'Africa/Nairobi',
        ])
        ->assertRedirect();

    $employee = HrEmployee::query()
        ->where('company_id', $company->id)
        ->where('employee_number', 'EMP-ACCESS-001')
        ->first();

    expect($employee)->not->toBeNull();
    expect($employee?->requires_system_access)->toBeFalse();
    expect($employee?->system_access_status)->toBe(HrEmployee::ACCESS_STATUS_NONE);
    expect($employee?->invite_id)->toBeNull();
    expect($employee?->user_id)->toBeNull();

    Queue::assertNothingPushed();
});

test('hr manager can create an employee with a pending system access invite', function () {
    $this->seed(CoreRolesSeeder::class);
    Queue::fake();

    [$manager, $company] = makeActiveCompanyMember();
    assignHrEmployeeAccessRole($manager, $company->id, 'hr_manager');

    $salesManagerRole = Role::query()
        ->whereNull('company_id')
        ->where('slug', 'sales_manager')
        ->firstOrFail();

    actingAs($manager)
        ->post(route('company.hr.employees.store'), [
            'user_id' => '',
            'requires_system_access' => true,
            'department_id' => '',
            'department_name' => 'Sales',
            'designation_id' => '',
            'designation_name' => 'Sales Manager',
            'system_role_id' => $salesManagerRole->id,
            'employee_number' => 'EMP-ACCESS-002',
            'employment_status' => HrEmployee::STATUS_ACTIVE,
            'employment_type' => HrEmployee::TYPE_FULL_TIME,
            'first_name' => 'Meron',
            'last_name' => 'Invite',
            'work_email' => 'meron.invite@example.test',
            'login_email' => 'meron.invite@example.test',
            'hire_date' => now()->toDateString(),
            'timezone' => 'Africa/Nairobi',
        ])
        ->assertRedirect();

    $employee = HrEmployee::query()
        ->where('company_id', $company->id)
        ->where('employee_number', 'EMP-ACCESS-002')
        ->first();

    expect($employee)->not->toBeNull();
    expect($employee?->requires_system_access)->toBeTrue();
    expect($employee?->system_access_status)->toBe(HrEmployee::ACCESS_STATUS_PENDING_INVITE);
    expect((string) $employee?->system_role_id)->toBe($salesManagerRole->id);
    expect($employee?->login_email)->toBe('meron.invite@example.test');
    expect($employee?->invite_id)->not->toBeNull();

    $invite = Invite::query()->findOrFail($employee?->invite_id);

    expect((string) $invite->employee_id)->toBe($employee?->id);
    expect((string) $invite->company_role_id)->toBe($salesManagerRole->id);
    expect($invite->email)->toBe('meron.invite@example.test');
    expect($invite->role)->toBe('company_member');

    Queue::assertPushed(SendInviteLinkMail::class);
});

test('hr manager can resend cancel and reactivate a pending employee access invite', function () {
    $this->seed(CoreRolesSeeder::class);
    Queue::fake();

    [$manager, $company] = makeActiveCompanyMember();
    assignHrEmployeeAccessRole($manager, $company->id, 'hr_manager');

    $projectRole = Role::query()
        ->whereNull('company_id')
        ->where('slug', 'project_manager')
        ->firstOrFail();

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-ACCESS-RETRY',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'requires_system_access' => true,
        'system_access_status' => HrEmployee::ACCESS_STATUS_PENDING_INVITE,
        'system_role_id' => $projectRole->id,
        'first_name' => 'Helen',
        'last_name' => 'Pending',
        'display_name' => 'Helen Pending',
        'work_email' => 'helen.pending@example.test',
        'login_email' => 'helen.pending@example.test',
        'hire_date' => now()->toDateString(),
        'timezone' => 'Africa/Nairobi',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $invite = Invite::create([
        'email' => 'helen.pending@example.test',
        'name' => 'Helen Pending',
        'role' => 'company_member',
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'company_role_id' => $projectRole->id,
        'token' => 'original-token',
        'expires_at' => now()->addDays(7),
        'delivery_status' => Invite::DELIVERY_SENT,
        'delivery_attempts' => 1,
        'created_by' => $manager->id,
    ]);

    $employee->forceFill([
        'invite_id' => $invite->id,
    ])->save();

    actingAs($manager)
        ->post(route('company.hr.employees.access.resend', $employee))
        ->assertRedirect(route('company.hr.employees.show', $employee));

    $invite->refresh();

    expect($invite->token)->not->toBe('original-token');
    expect($invite->delivery_status)->toBe(Invite::DELIVERY_PENDING);

    Queue::assertPushed(SendInviteLinkMail::class, 1);

    actingAs($manager)
        ->delete(route('company.hr.employees.access.cancel', $employee))
        ->assertRedirect(route('company.hr.employees.show', $employee));

    $employee->refresh();

    expect($employee->system_access_status)->toBe(HrEmployee::ACCESS_STATUS_SUSPENDED);
    expect($employee->invite_id)->toBeNull();
    expect(Invite::query()->whereKey($invite->id)->exists())->toBeFalse();

    Queue::fake();

    actingAs($manager)
        ->post(route('company.hr.employees.access.reactivate', $employee))
        ->assertRedirect(route('company.hr.employees.show', $employee));

    $employee->refresh();

    expect($employee->system_access_status)->toBe(HrEmployee::ACCESS_STATUS_PENDING_INVITE);
    expect($employee->invite_id)->not->toBeNull();

    Queue::assertPushed(SendInviteLinkMail::class, 1);
});

test('hr manager can link an existing company user to an employee and assign a role immediately', function () {
    $this->seed(CoreRolesSeeder::class);
    Queue::fake();

    [$manager, $company] = makeActiveCompanyMember();
    assignHrEmployeeAccessRole($manager, $company->id, 'hr_manager');

    $existingUser = User::factory()->create([
        'email' => 'existing.employee@example.test',
    ]);
    $company->users()->syncWithoutDetaching([
        $existingUser->id => [
            'role_id' => null,
            'is_owner' => false,
        ],
    ]);
    assignHrEmployeeAccessRole($existingUser, $company->id, 'member');

    $inventoryRole = Role::query()
        ->whereNull('company_id')
        ->where('slug', 'inventory_manager')
        ->firstOrFail();

    actingAs($manager)
        ->post(route('company.hr.employees.store'), [
            'user_id' => $existingUser->id,
            'requires_system_access' => true,
            'department_id' => '',
            'department_name' => 'Warehouse',
            'designation_id' => '',
            'designation_name' => 'Inventory Lead',
            'system_role_id' => $inventoryRole->id,
            'employee_number' => 'EMP-ACCESS-003',
            'employment_status' => HrEmployee::STATUS_ACTIVE,
            'employment_type' => HrEmployee::TYPE_FULL_TIME,
            'first_name' => 'Rahel',
            'last_name' => 'Linked',
            'work_email' => 'existing.employee@example.test',
            'login_email' => '',
            'hire_date' => now()->toDateString(),
            'timezone' => 'Africa/Nairobi',
        ])
        ->assertRedirect();

    $employee = HrEmployee::query()
        ->where('company_id', $company->id)
        ->where('employee_number', 'EMP-ACCESS-003')
        ->first();

    expect($employee)->not->toBeNull();
    expect((string) $employee?->user_id)->toBe($existingUser->id);
    expect($employee?->requires_system_access)->toBeTrue();
    expect($employee?->system_access_status)->toBe(HrEmployee::ACCESS_STATUS_ACTIVE);
    expect((string) $employee?->system_role_id)->toBe($inventoryRole->id);
    expect($employee?->invite_id)->toBeNull();

    expect(
        DB::table('company_users')
            ->where('company_id', $company->id)
            ->where('user_id', $existingUser->id)
            ->value('role_id')
    )->toBe($inventoryRole->id);

    Queue::assertNothingPushed();
});

test('hr manager can change role and deactivate then reactivate linked employee access', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignHrEmployeeAccessRole($manager, $company->id, 'hr_manager');

    $existingUser = User::factory()->create([
        'email' => 'reactivate.employee@example.test',
    ]);
    $company->users()->syncWithoutDetaching([
        $existingUser->id => [
            'role_id' => null,
            'is_owner' => false,
        ],
    ]);
    assignHrEmployeeAccessRole($existingUser, $company->id, 'inventory_manager');

    $inventoryRole = Role::query()
        ->whereNull('company_id')
        ->where('slug', 'inventory_manager')
        ->firstOrFail();

    $financeRole = Role::query()
        ->whereNull('company_id')
        ->where('slug', 'finance_manager')
        ->firstOrFail();

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-ACCESS-ROLE',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'user_id' => $existingUser->id,
        'requires_system_access' => true,
        'system_access_status' => HrEmployee::ACCESS_STATUS_ACTIVE,
        'system_role_id' => $inventoryRole->id,
        'first_name' => 'Liya',
        'last_name' => 'Active',
        'display_name' => 'Liya Active',
        'work_email' => 'reactivate.employee@example.test',
        'login_email' => 'reactivate.employee@example.test',
        'hire_date' => now()->toDateString(),
        'timezone' => 'Africa/Nairobi',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->patch(route('company.hr.employees.access.role', $employee), [
            'role_id' => $financeRole->id,
        ])
        ->assertRedirect(route('company.hr.employees.show', $employee));

    $employee->refresh();

    expect((string) $employee->system_role_id)->toBe($financeRole->id);
    expect(
        DB::table('company_users')
            ->where('company_id', $company->id)
            ->where('user_id', $existingUser->id)
            ->value('role_id')
    )->toBe($financeRole->id);

    actingAs($manager)
        ->patch(route('company.hr.employees.access.deactivate', $employee))
        ->assertRedirect(route('company.hr.employees.show', $employee));

    $employee->refresh();
    $existingUser->refresh();

    expect($employee->system_access_status)->toBe(HrEmployee::ACCESS_STATUS_SUSPENDED);
    expect(
        DB::table('company_users')
            ->where('company_id', $company->id)
            ->where('user_id', $existingUser->id)
            ->exists()
    )->toBeFalse();
    expect($existingUser->current_company_id)->toBeNull();

    actingAs($manager)
        ->post(route('company.hr.employees.access.reactivate', $employee))
        ->assertRedirect(route('company.hr.employees.show', $employee));

    $employee->refresh();

    expect($employee->system_access_status)->toBe(HrEmployee::ACCESS_STATUS_ACTIVE);
    expect(
        DB::table('company_users')
            ->where('company_id', $company->id)
            ->where('user_id', $existingUser->id)
            ->value('role_id')
    )->toBe($financeRole->id);
});

test('accepting an employee access invite links the employee record and assigns the selected role', function () {
    $this->seed(CoreRolesSeeder::class);

    [$creator, $company] = makeActiveCompanyMember();
    assignHrEmployeeAccessRole($creator, $company->id, 'hr_manager');

    $financeRole = Role::query()
        ->whereNull('company_id')
        ->where('slug', 'finance_manager')
        ->firstOrFail();

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-ACCESS-004',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'requires_system_access' => true,
        'system_access_status' => HrEmployee::ACCESS_STATUS_PENDING_INVITE,
        'system_role_id' => $financeRole->id,
        'first_name' => 'Sami',
        'last_name' => 'Accepted',
        'display_name' => 'Sami Accepted',
        'work_email' => 'sami.accepted@example.test',
        'login_email' => 'sami.accepted@example.test',
        'hire_date' => now()->toDateString(),
        'timezone' => 'Africa/Nairobi',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    $invite = Invite::create([
        'email' => 'sami.accepted@example.test',
        'name' => 'Sami Accepted',
        'role' => 'company_member',
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'company_role_id' => $financeRole->id,
        'token' => Str::random(40),
        'expires_at' => now()->addDays(7),
        'delivery_status' => Invite::DELIVERY_PENDING,
        'delivery_attempts' => 0,
        'created_by' => $creator->id,
    ]);

    $employee->forceFill([
        'invite_id' => $invite->id,
    ])->save();

    post(route('invites.accept.store', ['token' => $invite->token]), [
        'name' => 'Sami Accepted',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    $acceptedUser = User::query()
        ->where('email', 'sami.accepted@example.test')
        ->first();

    expect($acceptedUser)->not->toBeNull();

    $employee->refresh();

    expect((string) $employee->user_id)->toBe($acceptedUser?->id);
    expect($employee->requires_system_access)->toBeTrue();
    expect($employee->system_access_status)->toBe(HrEmployee::ACCESS_STATUS_ACTIVE);
    expect((string) $employee->system_role_id)->toBe($financeRole->id);
    expect((string) $employee->invite_id)->toBe($invite->id);

    expect(
        DB::table('company_users')
            ->where('company_id', $company->id)
            ->where('user_id', $acceptedUser?->id)
            ->value('role_id')
    )->toBe($financeRole->id);
});
