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
        Schema::create('accounting_bank_statement_imports', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('journal_id')->constrained('accounting_journals')->cascadeOnDelete();
            $table->foreignUuid('reconciled_batch_id')
                ->nullable()
                ->constrained('accounting_bank_reconciliation_batches')
                ->nullOnDelete();
            $table->string('statement_reference', 128);
            $table->date('statement_date');
            $table->string('source_file_name', 255);
            $table->text('notes')->nullable();
            $table->foreignUuid('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'statement_date']);
            $table->index(['company_id', 'journal_id']);
            $table->index(['company_id', 'reconciled_batch_id']);
            $table->index('imported_by');
        });

        Schema::create('accounting_bank_statement_import_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bank_statement_import_id')
                ->constrained('accounting_bank_statement_imports')
                ->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->date('transaction_date')->nullable();
            $table->string('reference', 255)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('match_status', 24)->default('unmatched');
            $table->foreignUuid('payment_id')
                ->nullable()
                ->constrained('accounting_payments')
                ->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'bank_statement_import_id'], 'accounting_bank_stmt_lines_company_import_idx');
            $table->index(['company_id', 'match_status'], 'accounting_bank_stmt_lines_company_match_idx');
            $table->index(['company_id', 'payment_id'], 'accounting_bank_stmt_lines_company_payment_idx');
        });

        Schema::table('accounting_bank_reconciliation_items', function (Blueprint $table) {
            $table->date('statement_line_date')
                ->nullable()
                ->after('amount');
            $table->string('statement_line_reference', 255)
                ->nullable()
                ->after('statement_line_date');
            $table->text('statement_line_description')
                ->nullable()
                ->after('statement_line_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_bank_reconciliation_items', function (Blueprint $table) {
            $table->dropColumn([
                'statement_line_date',
                'statement_line_reference',
                'statement_line_description',
            ]);
        });

        Schema::dropIfExists('accounting_bank_statement_import_lines');
        Schema::dropIfExists('accounting_bank_statement_imports');
    }
};
