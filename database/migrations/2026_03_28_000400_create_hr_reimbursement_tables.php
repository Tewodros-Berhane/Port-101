<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_reimbursement_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('default_expense_account_reference')->nullable();
            $table->boolean('requires_receipt')->default(false);
            $table->boolean('is_project_rebillable')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('hr_reimbursement_claims', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUuid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('claim_number');
            $table->string('status');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignUuid('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('manager_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('finance_approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('manager_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('finance_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('accounting_invoice_id')->nullable()->constrained('accounting_invoices')->nullOnDelete();
            $table->foreignUuid('accounting_payment_id')->nullable()->constrained('accounting_payments')->nullOnDelete();
            $table->uuid('payslip_id')->nullable();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->timestamp('finance_approved_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'claim_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'approver_user_id']);
            $table->index(['company_id', 'accounting_invoice_id']);
        });

        Schema::create('hr_reimbursement_claim_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('claim_id')->constrained('hr_reimbursement_claims')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('hr_reimbursement_categories')->restrictOnDelete();
            $table->date('expense_date');
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->foreignUuid('receipt_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'claim_id']);
            $table->index(['company_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_reimbursement_claim_lines');
        Schema::dropIfExists('hr_reimbursement_claims');
        Schema::dropIfExists('hr_reimbursement_categories');
    }
};
