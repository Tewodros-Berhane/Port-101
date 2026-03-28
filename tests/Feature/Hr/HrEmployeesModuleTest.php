<?php

use App\Core\Attachments\Models\Attachment;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use Database\Seeders\CoreRolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function assignHrWorkspaceRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()
        ->where('slug', $roleSlug)
        ->whereNull('company_id')
        ->firstOrFail();

    $user->memberships()
        ->where('company_id', $companyId)
        ->update([
            'role_id' => $role->id,
            'is_owner' => false,
        ]);

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

test('hr manager can access hr workspace and manage employees contracts and documents', function () {
    $this->seed(CoreRolesSeeder::class);
    config()->set('core.attachments.scan.enabled', false);
    Storage::fake((string) config('core.attachments.disk', 'local'));

    [$manager, $company] = makeActiveCompanyMember();
    assignHrWorkspaceRole($manager, $company->id, 'hr_manager');

    actingAs($manager)
        ->get(route('company.modules.hr'))
        ->assertOk();

    actingAs($manager)
        ->get(route('company.hr.employees.create'))
        ->assertOk();

    actingAs($manager)
        ->post(route('company.hr.employees.store'), [
            'user_id' => '',
            'department_id' => '',
            'department_name' => 'People Operations',
            'designation_id' => '',
            'designation_name' => 'HR Generalist',
            'employee_number' => 'EMP-HR-001',
            'employment_status' => HrEmployee::STATUS_ACTIVE,
            'employment_type' => HrEmployee::TYPE_FULL_TIME,
            'first_name' => 'Selam',
            'last_name' => 'Kassa',
            'work_email' => 'selam@example.test',
            'personal_email' => 'selam.personal@example.test',
            'work_phone' => '+251911000000',
            'personal_phone' => '+251922000000',
            'date_of_birth' => '1995-02-02',
            'hire_date' => now()->toDateString(),
            'termination_date' => '',
            'manager_employee_id' => '',
            'attendance_approver_user_id' => '',
            'leave_approver_user_id' => '',
            'reimbursement_approver_user_id' => '',
            'timezone' => 'Africa/Nairobi',
            'country_code' => 'ET',
            'work_location' => 'HQ',
            'bank_account_reference' => 'ACC-001',
            'emergency_contact_name' => 'Jane',
            'emergency_contact_phone' => '+251933000000',
            'notes' => 'Initial HR employee record.',
        ])
        ->assertRedirect();

    $employee = HrEmployee::query()
        ->where('employee_number', 'EMP-HR-001')
        ->first();

    expect($employee)->not->toBeNull();

    actingAs($manager)
        ->get(route('company.hr.employees.show', $employee))
        ->assertOk()
        ->assertSee('Selam Kassa');

    actingAs($manager)
        ->post(route('company.hr.contracts.store', $employee), [
            'contract_number' => 'CON-HR-001',
            'status' => HrEmployeeContract::STATUS_ACTIVE,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'pay_frequency' => HrEmployeeContract::PAY_FREQUENCY_MONTHLY,
            'salary_basis' => HrEmployeeContract::SALARY_BASIS_FIXED,
            'base_salary_amount' => 2400,
            'hourly_rate' => '',
            'currency_id' => '',
            'working_days_per_week' => 5,
            'standard_hours_per_day' => 8,
            'is_payroll_eligible' => true,
            'notes' => 'Employee payroll foundation contract.',
        ])
        ->assertRedirect();

    $contract = HrEmployeeContract::query()
        ->where('employee_id', $employee?->id)
        ->where('contract_number', 'CON-HR-001')
        ->first();

    expect($contract)->not->toBeNull();

    actingAs($manager)
        ->put(route('company.hr.contracts.update', $contract), [
            'contract_number' => 'CON-HR-001',
            'status' => HrEmployeeContract::STATUS_ACTIVE,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'pay_frequency' => HrEmployeeContract::PAY_FREQUENCY_MONTHLY,
            'salary_basis' => HrEmployeeContract::SALARY_BASIS_FIXED,
            'base_salary_amount' => 2600,
            'hourly_rate' => '',
            'currency_id' => '',
            'working_days_per_week' => 5,
            'standard_hours_per_day' => 8,
            'is_payroll_eligible' => true,
            'notes' => 'Updated salary baseline.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('hr_employee_contracts', [
        'id' => $contract?->id,
        'base_salary_amount' => 2600,
    ]);

    actingAs($manager)
        ->post(route('company.hr.documents.store', $employee), [
            'document_type' => 'contract',
            'document_name' => 'Signed contract',
            'valid_until' => now()->addYear()->toDateString(),
            'is_private' => true,
            'file' => UploadedFile::fake()->create('contract.pdf', 128, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('hr_employee_documents', [
        'employee_id' => $employee?->id,
        'document_type' => 'contract',
        'document_name' => 'Signed contract',
    ]);

    expect(Attachment::query()->where('attachable_id', $employee?->id)->exists())->toBeTrue();
});

test('member without hr permissions cannot create employee records', function () {
    $this->seed(CoreRolesSeeder::class);

    [$member, $company] = makeActiveCompanyMember();
    assignHrWorkspaceRole($member, $company->id, 'member');

    actingAs($member)
        ->get(route('company.hr.employees.create'))
        ->assertForbidden();

    actingAs($member)
        ->post(route('company.hr.employees.store'), [
            'employment_status' => HrEmployee::STATUS_ACTIVE,
            'employment_type' => HrEmployee::TYPE_FULL_TIME,
            'first_name' => 'Blocked',
            'last_name' => 'User',
        ])
        ->assertForbidden();
});
