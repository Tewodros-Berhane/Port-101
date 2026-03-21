<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounting_bank_reconciliation_batches', function (Blueprint $table) {
            $table->foreignUuid('unreconciled_by')
                ->nullable()
                ->after('reconciled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('unreconciled_at')
                ->nullable()
                ->after('unreconciled_by');
            $table->string('unreconcile_reason', 255)
                ->nullable()
                ->after('unreconciled_at');

            $table->index('unreconciled_by');
            $table->index(
                ['company_id', 'unreconciled_at'],
                'accounting_bank_rec_batches_company_unrec_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_bank_reconciliation_batches', function (Blueprint $table) {
            $table->dropIndex('accounting_bank_rec_batches_company_unrec_idx');
            $table->dropIndex(['unreconciled_by']);
            $table->dropConstrainedForeignId('unreconciled_by');
            $table->dropColumn(['unreconciled_at', 'unreconcile_reason']);
        });
    }
};
