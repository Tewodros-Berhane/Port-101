<?php

use App\Core\MasterData\Models\Currency;
use App\Modules\Hr\Models\HrCompensationAssignment;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrPayrollPeriod;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayrollWorkEntry;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrPayslipLine;
use App\Modules\Hr\Models\HrSalaryStructure;
use App\Modules\Hr\Models\HrSalaryStructureLine;
use Illuminate\Support\Facades\Schema;

test('hr payroll foundation tables exist and relations persist', function () {
    foreach ([
        'hr_salary_structures',
        'hr_salary_structure_lines',
        'hr_compensation_assignments',
        'hr_payroll_periods',
        'hr_payroll_runs',
        'hr_payroll_work_entries',
        'hr_payslips',
        'hr_payslip_lines',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    [$user, $company] = makeActiveCompanyMember();

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

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'employee_number' => 'EMP-PAY-001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Ada',
        'last_name' => 'Payroll',
        'display_name' => 'Ada Payroll',
        'hire_date' => now()->toDateString(),
        'timezone' => 'UTC',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $contract = HrEmployeeContract::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'contract_number' => 'CON-PAY-001',
        'status' => HrEmployeeContract::STATUS_ACTIVE,
        'start_date' => now()->toDateString(),
        'pay_frequency' => HrEmployeeContract::PAY_FREQUENCY_MONTHLY,
        'salary_basis' => HrEmployeeContract::SALARY_BASIS_FIXED,
        'base_salary_amount' => 1200,
        'currency_id' => $currency->id,
        'working_days_per_week' => 5,
        'standard_hours_per_day' => 8,
        'is_payroll_eligible' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $structure = HrSalaryStructure::create([
        'company_id' => $company->id,
        'name' => 'Standard',
        'code' => 'STD',
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $structureLine = HrSalaryStructureLine::create([
        'company_id' => $company->id,
        'salary_structure_id' => $structure->id,
        'line_type' => HrSalaryStructureLine::TYPE_EARNING,
        'calculation_type' => HrSalaryStructureLine::CALCULATION_FIXED,
        'code' => 'BASIC',
        'name' => 'Basic salary',
        'line_order' => 1,
        'amount' => 1200,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $assignment = HrCompensationAssignment::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'contract_id' => $contract->id,
        'salary_structure_id' => $structure->id,
        'currency_id' => $currency->id,
        'effective_from' => now()->toDateString(),
        'pay_frequency' => HrEmployeeContract::PAY_FREQUENCY_MONTHLY,
        'salary_basis' => HrEmployeeContract::SALARY_BASIS_FIXED,
        'base_salary_amount' => 1200,
        'is_active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $period = HrPayrollPeriod::create([
        'company_id' => $company->id,
        'name' => 'April 2026',
        'pay_frequency' => HrEmployeeContract::PAY_FREQUENCY_MONTHLY,
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
        'payment_date' => '2026-04-30',
        'status' => HrPayrollPeriod::STATUS_OPEN,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $run = HrPayrollRun::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'run_number' => 'PR-202604-0001',
        'status' => HrPayrollRun::STATUS_DRAFT,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $workEntry = HrPayrollWorkEntry::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'payroll_period_id' => $period->id,
        'payroll_run_id' => $run->id,
        'entry_type' => HrPayrollWorkEntry::TYPE_WORKED_TIME,
        'quantity' => 8,
        'amount_reference' => 15,
        'status' => HrPayrollWorkEntry::STATUS_CONFIRMED,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $payslip = HrPayslip::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'compensation_assignment_id' => $assignment->id,
        'currency_id' => $currency->id,
        'payslip_number' => 'PSL-PR-202604-0001-EMP-PAY-001',
        'status' => HrPayslip::STATUS_DRAFT,
        'gross_pay' => 1200,
        'total_deductions' => 0,
        'reimbursement_amount' => 0,
        'net_pay' => 1200,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $line = HrPayslipLine::create([
        'company_id' => $company->id,
        'payslip_id' => $payslip->id,
        'line_type' => HrPayslipLine::TYPE_EARNING,
        'code' => 'BASIC',
        'name' => 'Basic salary',
        'line_order' => 1,
        'quantity' => 1,
        'rate' => 1200,
        'amount' => 1200,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    expect($structure->lines()->count())->toBe(1);
    expect($structureLine->salaryStructure?->is($structure))->toBeTrue();
    expect($assignment->employee?->is($employee))->toBeTrue();
    expect($assignment->contract?->is($contract))->toBeTrue();
    expect($assignment->salaryStructure?->is($structure))->toBeTrue();
    expect($period->payrollRuns()->count())->toBe(1);
    expect($period->workEntries()->count())->toBe(1);
    expect($period->payslips()->count())->toBe(1);
    expect($run->workEntries()->count())->toBe(1);
    expect($run->payslips()->count())->toBe(1);
    expect($workEntry->payrollRun?->is($run))->toBeTrue();
    expect($payslip->lines()->count())->toBe(1);
    expect($payslip->compensationAssignment?->is($assignment))->toBeTrue();
    expect($line->payslip?->is($payslip))->toBeTrue();
});
