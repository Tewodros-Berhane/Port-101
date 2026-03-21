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
        Schema::table('accounting_manual_journals', function (Blueprint $table) {
            $table->boolean('requires_approval')
                ->default(false)
                ->after('status');
            $table->string('approval_status', 24)
                ->default('not_required')
                ->after('requires_approval');
            $table->timestamp('approval_requested_at')
                ->nullable()
                ->after('approval_status');
            $table->foreignUuid('approved_by')
                ->nullable()
                ->after('approval_requested_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');
            $table->foreignUuid('rejected_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('rejected_at')
                ->nullable()
                ->after('rejected_by');
            $table->string('rejection_reason', 255)
                ->nullable()
                ->after('rejected_at');

            $table->index(['company_id', 'approval_status'], 'accounting_manual_journals_company_approval_idx');
            $table->index('approved_by');
            $table->index('rejected_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_manual_journals', function (Blueprint $table) {
            $table->dropIndex('accounting_manual_journals_company_approval_idx');
            $table->dropIndex(['approved_by']);
            $table->dropIndex(['rejected_by']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'requires_approval',
                'approval_status',
                'approval_requested_at',
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
