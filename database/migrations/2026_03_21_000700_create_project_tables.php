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
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignUuid('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignUuid('currency_id')->constrained('currencies');
            $table->string('project_code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('billing_type', 24)->default('time_and_material');
            $table->foreignUuid('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('budget_amount', 15, 2)->nullable();
            $table->decimal('budget_hours', 15, 2)->nullable();
            $table->decimal('actual_cost_amount', 15, 2)->default(0);
            $table->decimal('actual_billable_amount', 15, 2)->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->string('health_status', 24)->default('on_track');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'project_code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'project_manager_id']);
            $table->index('sales_order_id');
            $table->index('currency_id');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('project_role', 24)->default('member');
            $table->decimal('allocation_percent', 5, 2)->nullable();
            $table->decimal('hourly_cost_rate', 15, 2)->nullable();
            $table->decimal('hourly_bill_rate', 15, 2)->nullable();
            $table->boolean('is_billable_by_default')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'user_id']);
            $table->index(['company_id', 'project_id']);
            $table->index(['company_id', 'user_id']);
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('project_stages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('color', 32)->nullable();
            $table->boolean('is_closed_stage')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'sequence']);
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('stage_id')->nullable()->constrained('project_stages')->nullOnDelete();
            $table->uuid('parent_task_id')->nullable();
            $table->foreignUuid('customer_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('task_number', 64);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->string('priority', 16)->default('medium');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('estimated_hours', 15, 2)->nullable();
            $table->decimal('actual_hours', 15, 2)->default(0);
            $table->boolean('is_billable')->default(true);
            $table->string('billing_status', 24)->default('not_ready');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'task_number']);
            $table->index(['company_id', 'project_id', 'status']);
            $table->index(['company_id', 'assigned_to', 'status']);
            $table->index('stage_id');
            $table->index('parent_task_id');
            $table->index('customer_id');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreign('parent_task_id')
                ->references('id')
                ->on('project_tasks')
                ->nullOnDelete();
        });

        Schema::create('project_timesheets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('work_date');
            $table->text('description')->nullable();
            $table->decimal('hours', 15, 2);
            $table->boolean('is_billable')->default(true);
            $table->decimal('cost_rate', 15, 2)->default(0);
            $table->decimal('bill_rate', 15, 2)->default(0);
            $table->decimal('cost_amount', 15, 2)->default(0);
            $table->decimal('billable_amount', 15, 2)->default(0);
            $table->string('approval_status', 24)->default('draft');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('invoice_status', 24)->default('not_ready');
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'work_date']);
            $table->index(['company_id', 'user_id', 'work_date']);
            $table->index(['company_id', 'approval_status']);
            $table->index(['company_id', 'invoice_status']);
            $table->index(['source_type', 'source_id']);
            $table->index('task_id');
            $table->index('approved_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sequence')->default(1);
            $table->string('status', 24)->default('draft');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('invoice_status', 24)->default('not_ready');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
            $table->index(['company_id', 'invoice_status']);
            $table->index('approved_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('project_billables', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('billable_type', 24)->default('manual');
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('customer_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->text('description');
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->foreignUuid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('status', 24)->default('draft');
            $table->string('approval_status', 24)->default('not_required');
            $table->foreignUuid('invoice_id')->nullable()->constrained('accounting_invoices')->nullOnDelete();
            $table->string('invoice_line_reference', 128)->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status']);
            $table->index(['company_id', 'customer_id', 'status']);
            $table->index(['company_id', 'approval_status']);
            $table->index(['source_type', 'source_id']);
            $table->index('currency_id');
            $table->index('invoice_id');
            $table->index('approved_by');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_billables');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_timesheets');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_stages');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
