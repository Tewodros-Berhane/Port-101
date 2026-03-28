<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_salary_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('hr_salary_structure_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('salary_structure_id')->constrained('hr_salary_structures')->cascadeOnDelete();
            $table->string('line_type', 24);
            $table->string('calculation_type', 24);
            $table->string('code', 64);
            $table->string('name');
            $table->unsignedInteger('line_order')->default(1);
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('percentage_rate', 8, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'salary_structure_id'], 'hr_salary_structure_lines_structure_idx');
            $table->index(['company_id', 'line_type'], 'hr_salary_structure_lines_type_idx');
        });

        Schema::create('hr_compensation_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained('hr_employee_contracts')->nullOnDelete();
            $table->foreignUuid('salary_structure_id')->nullable()->constrained('hr_salary_structures')->nullOnDelete();
            $table->foreignUuid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('pay_frequency', 24);
            $table->string('salary_basis', 24);
            $table->decimal('base_salary_amount', 15, 2)->nullable();
            $table->decimal('hourly_rate', 15, 2)->nullable();
            $table->string('payroll_group', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id', 'effective_from'], 'hr_comp_assignments_employee_from_idx');
            $table->index(['company_id', 'effective_to'], 'hr_comp_assignments_effective_to_idx');
            $table->index(['company_id', 'is_active'], 'hr_comp_assignments_active_idx');
        });

        Schema::create('hr_payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('pay_frequency', 24);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date');
            $table->string('status', 24)->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status'], 'hr_payroll_periods_status_idx');
            $table->index(['company_id', 'start_date', 'end_date'], 'hr_payroll_periods_dates_idx');
        });

        Schema::create('hr_payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payroll_period_id')->constrained('hr_payroll_periods')->cascadeOnDelete();
            $table->foreignUuid('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('accounting_manual_journal_id')->nullable()->constrained('accounting_manual_journals')->nullOnDelete();
            $table->string('run_number', 64);
            $table->string('status', 24)->default('draft');
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_reimbursements', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'run_number']);
            $table->unique(['company_id', 'payroll_period_id']);
            $table->index(['company_id', 'status'], 'hr_payroll_runs_status_idx');
        });

        Schema::create('hr_payroll_work_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUuid('payroll_period_id')->constrained('hr_payroll_periods')->cascadeOnDelete();
            $table->foreignUuid('payroll_run_id')->nullable()->constrained('hr_payroll_runs')->cascadeOnDelete();
            $table->string('entry_type', 32);
            $table->string('source_type', 191)->nullable();
            $table->uuid('source_id')->nullable();
            $table->dateTime('from_datetime')->nullable();
            $table->dateTime('to_datetime')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('amount_reference', 15, 2)->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('conflict_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'payroll_period_id', 'employee_id'], 'hr_payroll_work_entries_period_employee_idx');
            $table->index(['company_id', 'payroll_run_id'], 'hr_payroll_work_entries_run_idx');
            $table->index(['company_id', 'status'], 'hr_payroll_work_entries_status_idx');
            $table->index(['company_id', 'source_type', 'source_id'], 'hr_payroll_work_entries_source_idx');
        });

        Schema::create('hr_payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUuid('payroll_run_id')->constrained('hr_payroll_runs')->cascadeOnDelete();
            $table->foreignUuid('payroll_period_id')->constrained('hr_payroll_periods')->cascadeOnDelete();
            $table->foreignUuid('compensation_assignment_id')->nullable()->constrained('hr_compensation_assignments')->nullOnDelete();
            $table->foreignUuid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignUuid('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payslip_number', 64);
            $table->string('status', 24)->default('draft');
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('reimbursement_amount', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'payslip_number']);
            $table->unique(['company_id', 'payroll_run_id', 'employee_id']);
            $table->index(['company_id', 'status'], 'hr_payslips_status_idx');
        });

        Schema::create('hr_payslip_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payslip_id')->constrained('hr_payslips')->cascadeOnDelete();
            $table->string('line_type', 24);
            $table->string('code', 64);
            $table->string('name');
            $table->unsignedInteger('line_order')->default(1);
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('source_type', 191)->nullable();
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'payslip_id'], 'hr_payslip_lines_payslip_idx');
            $table->index(['company_id', 'line_type'], 'hr_payslip_lines_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payslip_lines');
        Schema::dropIfExists('hr_payslips');
        Schema::dropIfExists('hr_payroll_work_entries');
        Schema::dropIfExists('hr_payroll_runs');
        Schema::dropIfExists('hr_payroll_periods');
        Schema::dropIfExists('hr_compensation_assignments');
        Schema::dropIfExists('hr_salary_structure_lines');
        Schema::dropIfExists('hr_salary_structures');
    }
};
