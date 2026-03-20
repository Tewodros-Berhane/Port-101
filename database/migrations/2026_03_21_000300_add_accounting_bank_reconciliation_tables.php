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
        Schema::table('accounting_payments', function (Blueprint $table) {
            $table->foreignUuid('bank_reconciled_by')
                ->nullable()
                ->after('reconciled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('bank_reconciled_at')
                ->nullable()
                ->after('bank_reconciled_by');

            $table->index('bank_reconciled_by');
            $table->index(['company_id', 'bank_reconciled_at'], 'accounting_payments_company_bank_rec_idx');
        });

        Schema::create('accounting_bank_reconciliation_batches', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('journal_id')->constrained('accounting_journals')->cascadeOnDelete();
            $table->string('statement_reference', 128);
            $table->date('statement_date');
            $table->text('notes')->nullable();
            $table->foreignUuid('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'statement_date']);
            $table->index(['company_id', 'journal_id']);
            $table->index('reconciled_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('accounting_bank_reconciliation_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('accounting_bank_reconciliation_batches')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained('accounting_payments')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'batch_id'], 'accounting_bank_rec_items_company_batch_idx');
            $table->index(['company_id', 'payment_id'], 'accounting_bank_rec_items_company_payment_idx');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_bank_reconciliation_items');
        Schema::dropIfExists('accounting_bank_reconciliation_batches');

        Schema::table('accounting_payments', function (Blueprint $table) {
            $table->dropIndex('accounting_payments_company_bank_rec_idx');
            $table->dropIndex(['bank_reconciled_by']);
            $table->dropConstrainedForeignId('bank_reconciled_by');
            $table->dropColumn('bank_reconciled_at');
        });
    }
};
