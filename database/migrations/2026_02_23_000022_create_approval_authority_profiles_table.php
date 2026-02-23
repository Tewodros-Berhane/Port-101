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
        Schema::create('approval_authority_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('module', 50);
            $table->string('action', 80);
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('max_risk_level', 20)->nullable();
            $table->boolean('requires_separate_requester')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'module', 'action'], 'approval_profiles_company_module_action_idx');
            $table->index(['user_id', 'is_active'], 'approval_profiles_user_active_idx');
            $table->index(['role_id', 'is_active'], 'approval_profiles_role_active_idx');
            $table->unique(['company_id', 'module', 'action', 'user_id'], 'approval_profiles_company_module_action_user_unique');
            $table->unique(['company_id', 'module', 'action', 'role_id'], 'approval_profiles_company_module_action_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_authority_profiles');
    }
};
