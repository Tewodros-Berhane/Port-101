<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->uuid('manager_employee_id')->nullable();
            $table->foreignUuid('leave_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('attendance_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reimbursement_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payroll_cost_center_reference', 64)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'manager_employee_id']);
        });

        Schema::create('hr_designations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('hr_employees', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignUuid('designation_id')->nullable()->constrained('hr_designations')->nullOnDelete();
            $table->string('employee_number', 64);
            $table->string('employment_status', 24)->default('draft');
            $table->string('employment_type', 24)->default('full_time');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('display_name');
            $table->string('work_email')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('personal_phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->uuid('manager_employee_id')->nullable();
            $table->foreignUuid('attendance_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('leave_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reimbursement_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('timezone', 64)->default('UTC');
            $table->string('country_code', 8)->nullable();
            $table->string('work_location')->nullable();
            $table->string('bank_account_reference')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_number']);
            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'employment_status']);
            $table->index(['company_id', 'department_id']);
            $table->index(['company_id', 'designation_id']);
            $table->index(['company_id', 'manager_employee_id']);
            $table->index(['company_id', 'work_email']);
        });

        Schema::table('hr_departments', function (Blueprint $table) {
            $table->foreign('manager_employee_id')
                ->references('id')
                ->on('hr_employees')
                ->nullOnDelete();
        });

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('manager_employee_id')
                ->references('id')
                ->on('hr_employees')
                ->nullOnDelete();
        });

        Schema::create('hr_employee_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('contract_number', 64);
            $table->string('status', 24)->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('pay_frequency', 16)->default('monthly');
            $table->string('salary_basis', 16)->default('fixed');
            $table->decimal('base_salary_amount', 15, 2)->nullable();
            $table->decimal('hourly_rate', 15, 2)->nullable();
            $table->foreignUuid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->unsignedTinyInteger('working_days_per_week')->default(5);
            $table->decimal('standard_hours_per_day', 5, 2)->default(8);
            $table->boolean('is_payroll_eligible')->default(true);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'contract_number']);
            $table->index(['company_id', 'employee_id', 'status']);
            $table->index(['company_id', 'start_date']);
            $table->index(['company_id', 'is_payroll_eligible']);
        });

        Schema::create('hr_employee_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUuid('attachment_id')->constrained('attachments')->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->string('document_name');
            $table->boolean('is_private')->default(true);
            $table->date('valid_until')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id', 'document_type']);
            $table->index(['company_id', 'is_private']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::table('hr_departments', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::dropIfExists('hr_employee_documents');
        Schema::dropIfExists('hr_employee_contracts');
        Schema::dropIfExists('hr_employees');
        Schema::dropIfExists('hr_designations');
        Schema::dropIfExists('hr_departments');
    }
};
