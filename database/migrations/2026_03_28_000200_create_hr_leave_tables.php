<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->string('unit', 16)->default('days');
            $table->boolean('requires_allocation')->default(true);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('allow_negative_balance')->default(false);
            $table->decimal('max_consecutive_days', 8, 2)->nullable();
            $table->string('color', 16)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'requires_allocation']);
            $table->index(['company_id', 'requires_approval']);
        });

        Schema::create('hr_leave_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'start_date']);
            $table->index(['company_id', 'end_date']);
            $table->index(['company_id', 'is_closed']);
        });

        Schema::create('hr_leave_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
            $table->foreignUuid('leave_period_id')->constrained('hr_leave_periods')->cascadeOnDelete();
            $table->decimal('allocated_amount', 10, 2)->default(0);
            $table->decimal('used_amount', 10, 2)->default(0);
            $table->decimal('balance_amount', 10, 2)->default(0);
            $table->decimal('carry_forward_amount', 10, 2)->default(0);
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_id', 'leave_type_id', 'leave_period_id'], 'hr_leave_allocations_unique');
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'leave_type_id']);
            $table->index(['company_id', 'leave_period_id']);
        });

        Schema::create('hr_leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
            $table->foreignUuid('leave_period_id')->constrained('hr_leave_periods')->cascadeOnDelete();
            $table->foreignUuid('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_number', 64);
            $table->string('status', 24)->default('draft');
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('duration_amount', 10, 2)->default(0);
            $table->boolean('is_half_day')->default(false);
            $table->text('reason')->nullable();
            $table->text('decision_notes')->nullable();
            $table->string('payroll_status', 24)->default('open');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'request_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'approver_user_id', 'status']);
            $table->index(['company_id', 'leave_period_id', 'status']);
            $table->index(['company_id', 'from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_requests');
        Schema::dropIfExists('hr_leave_allocations');
        Schema::dropIfExists('hr_leave_periods');
        Schema::dropIfExists('hr_leave_types');
    }
};
