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
        Schema::create('project_recurring_billings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignUuid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('frequency', 24)->default('monthly');
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->unsignedInteger('invoice_due_days')->default(30);
            $table->date('starts_on');
            $table->date('next_run_on');
            $table->date('ends_on')->nullable();
            $table->boolean('auto_create_invoice_draft')->default(false);
            $table->string('invoice_grouping', 24)->default('project');
            $table->string('status', 24)->default('draft');
            $table->timestamp('last_run_at')->nullable();
            $table->foreignUuid('last_invoice_id')->nullable()->constrained('accounting_invoices')->nullOnDelete();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'next_run_on']);
            $table->index(['company_id', 'project_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index('currency_id');
            $table->index('last_invoice_id');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('project_recurring_billing_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_recurring_billing_id')->constrained('project_recurring_billings')->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('cycle_key', 64);
            $table->date('scheduled_for');
            $table->string('cycle_label', 128);
            $table->string('status', 24)->default('ready');
            $table->foreignUuid('project_billable_id')->nullable()->constrained('project_billables')->nullOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('accounting_invoices')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_recurring_billing_id', 'cycle_key']);
            $table->index(['company_id', 'status', 'scheduled_for']);
            $table->index(['company_id', 'project_id', 'scheduled_for']);
            $table->index('project_billable_id');
            $table->index('invoice_id');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_recurring_billing_runs');
        Schema::dropIfExists('project_recurring_billings');
    }
};
