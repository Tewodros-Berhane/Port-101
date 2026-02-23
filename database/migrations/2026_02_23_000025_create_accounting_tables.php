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
        Schema::create('accounting_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignUuid('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->string('document_type', 24)->default('customer_invoice');
            $table->string('invoice_number', 64);
            $table->string('status', 24)->default('draft');
            $table->string('delivery_status', 24)->default('not_required');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_total', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id', 'document_type', 'status'], 'accounting_invoices_company_type_status_idx');
            $table->index(['company_id', 'delivery_status'], 'accounting_invoices_company_delivery_status_idx');
            $table->index('sales_order_id');
            $table->index('partner_id');
            $table->index('posted_by');
            $table->index('cancelled_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('accounting_invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('accounting_invoices')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'invoice_id']);
            $table->index('product_id');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('accounting_payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('accounting_invoices')->cascadeOnDelete();
            $table->string('payment_number', 64);
            $table->string('status', 24)->default('draft');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('method', 32)->nullable();
            $table->string('reference', 128)->nullable();
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignUuid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'payment_number']);
            $table->index(['company_id', 'status'], 'accounting_payments_company_status_idx');
            $table->index(['company_id', 'payment_date'], 'accounting_payments_company_payment_date_idx');
            $table->index('invoice_id');
            $table->index('posted_by');
            $table->index('reversed_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('accounting_reconciliation_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('accounting_invoices')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained('accounting_payments')->cascadeOnDelete();
            $table->string('entry_type', 16)->default('apply');
            $table->decimal('amount', 15, 2);
            $table->timestamp('reconciled_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'invoice_id']);
            $table->index(['company_id', 'payment_id']);
            $table->index('entry_type');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_reconciliation_entries');
        Schema::dropIfExists('accounting_payments');
        Schema::dropIfExists('accounting_invoice_lines');
        Schema::dropIfExists('accounting_invoices');
    }
};
