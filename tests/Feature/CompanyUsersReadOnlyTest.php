<?php

use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use Database\Seeders\CoreRolesSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('company users page is read only and points access changes back to hr employees', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();

    $manager->memberships()
        ->where('company_id', $company->id)
        ->update([
            'is_owner' => true,
        ]);

    $linkedUser = User::factory()->create([
        'email' => 'readonly.company.user@example.test',
    ]);

    $company->users()->syncWithoutDetaching([
        $linkedUser->id => [
            'role_id' => null,
            'is_owner' => false,
        ],
    ]);

    HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $linkedUser->id,
        'employee_number' => 'EMP-READONLY-001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'requires_system_access' => true,
        'system_access_status' => HrEmployee::ACCESS_STATUS_ACTIVE,
        'first_name' => 'Read',
        'last_name' => 'Only',
        'display_name' => 'Read Only',
        'work_email' => 'readonly.company.user@example.test',
        'login_email' => 'readonly.company.user@example.test',
        'hire_date' => now()->toDateString(),
        'timezone' => 'Africa/Nairobi',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->get(route('company.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company/users')
            ->where('members.1.employee.display_name', 'Read Only')
            ->missing('roles')
        );

    actingAs($manager)
        ->put("/company/users/{$manager->memberships()->where('company_id', $company->id)->value('id')}/role", [
            'role_id' => '00000000-0000-0000-0000-000000000000',
        ])
        ->assertNotFound();
});
