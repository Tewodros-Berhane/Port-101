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
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name', 120);
            $table->string('account_type', 24);
            $table->string('category', 40);
            $table->string('normal_balance', 6);
            $table->string('system_key', 48)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('allows_manual_posting')->default(false);
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'system_key']);
            $table->index(['company_id', 'account_type']);
            $table->index(['company_id', 'category']);
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name', 120);
            $table->string('journal_type', 24);
            $table->string('system_key', 48)->nullable();
            $table->foreignUuid('default_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->string('currency_code', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'system_key']);
            $table->index(['company_id', 'journal_type']);
            $table->index('default_account_id');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('accounting_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('journal_id')->constrained('accounting_journals')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounting_accounts')->cascadeOnDelete();
            $table->uuid('entry_group_uuid');
            $table->string('source_type', 180)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('source_action', 40);
            $table->date('transaction_date');
            $table->string('posting_reference', 64);
            $table->string('description', 255);
            $table->string('counterparty_name', 120)->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('currency_code', 3)->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'transaction_date'], 'accounting_ledger_entries_company_date_idx');
            $table->index(['company_id', 'journal_id'], 'accounting_ledger_entries_company_journal_idx');
            $table->index(['company_id', 'account_id'], 'accounting_ledger_entries_company_account_idx');
            $table->index(['company_id', 'source_type', 'source_id'], 'accounting_ledger_entries_company_source_idx');
            $table->index(['company_id', 'source_action'], 'accounting_ledger_entries_company_action_idx');
            $table->index('entry_group_uuid');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_ledger_entries');
        Schema::dropIfExists('accounting_journals');
        Schema::dropIfExists('accounting_accounts');
    }
};
