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
        Schema::create('accounting_manual_journals', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('journal_id')->constrained('accounting_journals')->cascadeOnDelete();
            $table->string('entry_number', 64);
            $table->string('status', 24)->default('draft');
            $table->date('entry_date');
            $table->string('reference', 128)->nullable();
            $table->string('description', 255);
            $table->foreignUuid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignUuid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 255)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'entry_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'journal_id']);
            $table->index('posted_by');
            $table->index('reversed_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('accounting_manual_journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('manual_journal_id')->constrained('accounting_manual_journals')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounting_accounts')->cascadeOnDelete();
            $table->unsignedInteger('line_order')->default(0);
            $table->string('description', 255)->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'manual_journal_id'], 'manual_journal_lines_company_journal_idx');
            $table->index(['company_id', 'account_id'], 'manual_journal_lines_company_account_idx');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_manual_journal_lines');
        Schema::dropIfExists('accounting_manual_journals');
    }
};
