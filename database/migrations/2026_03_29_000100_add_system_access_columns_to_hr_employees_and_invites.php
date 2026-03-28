<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->foreignUuid('employee_id')
                ->nullable()
                ->after('company_id')
                ->constrained('hr_employees')
                ->nullOnDelete();
            $table->foreignUuid('company_role_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('roles')
                ->nullOnDelete();

            $table->index(['company_id', 'employee_id'], 'invites_company_employee_idx');
            $table->index(['company_id', 'company_role_id'], 'invites_company_role_idx');
        });

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->boolean('requires_system_access')
                ->default(false)
                ->after('user_id');
            $table->string('system_access_status', 24)
                ->default('none')
                ->after('requires_system_access');
            $table->foreignUuid('system_role_id')
                ->nullable()
                ->after('designation_id')
                ->constrained('roles')
                ->nullOnDelete();
            $table->string('login_email')
                ->nullable()
                ->after('work_email');
            $table->foreignUuid('invite_id')
                ->nullable()
                ->after('reimbursement_approver_user_id')
                ->constrained('invites')
                ->nullOnDelete();

            $table->index(['company_id', 'requires_system_access'], 'hr_employees_system_access_required_idx');
            $table->index(['company_id', 'system_access_status'], 'hr_employees_system_access_status_idx');
            $table->unique(['company_id', 'login_email'], 'hr_employees_company_login_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropUnique('hr_employees_company_login_email_unique');
            $table->dropIndex('hr_employees_system_access_required_idx');
            $table->dropIndex('hr_employees_system_access_status_idx');
            $table->dropConstrainedForeignId('invite_id');
            $table->dropConstrainedForeignId('system_role_id');
            $table->dropColumn([
                'requires_system_access',
                'system_access_status',
                'login_email',
            ]);
        });

        Schema::table('invites', function (Blueprint $table) {
            $table->dropIndex('invites_company_employee_idx');
            $table->dropIndex('invites_company_role_idx');
            $table->dropConstrainedForeignId('company_role_id');
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
