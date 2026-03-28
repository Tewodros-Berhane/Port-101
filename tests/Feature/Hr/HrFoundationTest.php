<?php

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Currency;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Hr\Models\HrDesignation;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrEmployeeDocument;
use Illuminate\Support\Facades\Schema;

test('hr foundation tables exist and core employee relations persist', function () {
    foreach ([
        'hr_departments',
        'hr_designations',
        'hr_employees',
        'hr_employee_contracts',
        'hr_employee_documents',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    [$user, $company] = makeActiveCompanyMember();

    $department = HrDepartment::create([
        'company_id' => $company->id,
        'name' => 'Operations',
        'code' => 'OPS',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $designation = HrDesignation::create([
        'company_id' => $company->id,
        'name' => 'Operations Analyst',
        'code' => 'OPA',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'designation_id' => $designation->id,
        'employee_number' => 'EMP-0001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'display_name' => 'Ada Lovelace',
        'work_email' => 'ada@example.test',
        'hire_date' => now()->toDateString(),
        'timezone' => 'UTC',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $currency = Currency::create([
        'company_id' => $company->id,
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $contract = HrEmployeeContract::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'contract_number' => 'CON-EMP-0001-01',
        'status' => HrEmployeeContract::STATUS_ACTIVE,
        'start_date' => now()->toDateString(),
        'pay_frequency' => HrEmployeeContract::PAY_FREQUENCY_MONTHLY,
        'salary_basis' => HrEmployeeContract::SALARY_BASIS_FIXED,
        'base_salary_amount' => 1500,
        'currency_id' => $currency->id,
        'working_days_per_week' => 5,
        'standard_hours_per_day' => 8,
        'is_payroll_eligible' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $attachment = Attachment::create([
        'company_id' => $company->id,
        'attachable_type' => HrEmployee::class,
        'attachable_id' => $employee->id,
        'security_context' => 'hr_employee_document',
        'disk' => 'local',
        'path' => 'attachments/test.pdf',
        'file_name' => 'test.pdf',
        'original_name' => 'passport.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size' => 1024,
        'checksum' => 'checksum',
        'scan_status' => Attachment::SCAN_CLEAN,
        'uploaded_by' => $user->id,
    ]);

    $document = HrEmployeeDocument::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'attachment_id' => $attachment->id,
        'document_type' => 'passport',
        'document_name' => 'Passport copy',
        'is_private' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    expect($employee->department?->is($department))->toBeTrue();
    expect($employee->designation?->is($designation))->toBeTrue();
    expect($employee->user?->is($user))->toBeTrue();
    expect($employee->contracts()->count())->toBe(1);
    expect($employee->documents()->count())->toBe(1);
    expect($contract->employee?->is($employee))->toBeTrue();
    expect($document->attachment?->is($attachment))->toBeTrue();
});
