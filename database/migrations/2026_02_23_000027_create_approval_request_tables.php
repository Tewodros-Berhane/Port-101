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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('module', 32);
            $table->string('action', 64);
            $table->string('source_type');
            $table->uuid('source_id');
            $table->string('source_number', 96)->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignUuid('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency_code', 8)->nullable();
            $table->string('risk_level', 16)->nullable();
            $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_id', 'source_type', 'source_id'],
                'approval_requests_company_source_unique'
            );
            $table->index(['company_id', 'status'], 'approval_requests_company_status_idx');
            $table->index(['company_id', 'module', 'status'], 'approval_requests_company_module_status_idx');
            $table->index('action');
            $table->index('requested_by_user_id');
            $table->index('approved_by_user_id');
            $table->index('rejected_by_user_id');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order')->default(1);
            $table->foreignUuid('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->text('decision_notes')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'approval_request_id'], 'approval_steps_company_request_idx');
            $table->index(['approval_request_id', 'status'], 'approval_steps_request_status_idx');
            $table->index('approver_user_id');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
    }
};
