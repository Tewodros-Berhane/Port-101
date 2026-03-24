<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->index(['queue', 'reserved_at', 'available_at'], 'jobs_queue_reserved_available_idx');
            $table->index(['queue', 'available_at'], 'jobs_queue_available_idx');
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->index(['queue', 'failed_at'], 'failed_jobs_queue_failed_at_idx');
            $table->index('failed_at', 'failed_jobs_failed_at_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'notifications_notifiable_read_created_idx'
            );
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['company_id', 'action', 'created_at'], 'audit_logs_company_action_created_idx');
            $table->index(['company_id', 'user_id', 'created_at'], 'audit_logs_company_user_created_idx');
        });

        Schema::table('report_exports', function (Blueprint $table) {
            $table->index(['status', 'failed_at'], 'report_exports_status_failed_at_idx');
            $table->index(['company_id', 'status', 'failed_at'], 'report_exports_company_status_failed_at_idx');
        });

        Schema::table('integration_events', function (Blueprint $table) {
            $table->index(['company_id', 'published_at', 'occurred_at'], 'integration_events_company_published_occurred_idx');
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->index(['company_id', 'is_active', 'last_delivery_at'], 'webhook_endpoints_company_active_delivery_idx');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->index(['status', 'dead_lettered_at'], 'webhook_deliveries_status_dead_lettered_idx');
            $table->index(['company_id', 'status', 'dead_lettered_at'], 'webhook_deliveries_company_status_dead_lettered_idx');
            $table->index(['company_id', 'webhook_endpoint_id', 'status'], 'webhook_deliveries_company_endpoint_status_idx');
            $table->index(['company_id', 'status', 'next_retry_at'], 'webhook_deliveries_company_status_retry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex('webhook_deliveries_company_status_retry_idx');
            $table->dropIndex('webhook_deliveries_company_endpoint_status_idx');
            $table->dropIndex('webhook_deliveries_company_status_dead_lettered_idx');
            $table->dropIndex('webhook_deliveries_status_dead_lettered_idx');
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropIndex('webhook_endpoints_company_active_delivery_idx');
        });

        Schema::table('integration_events', function (Blueprint $table) {
            $table->dropIndex('integration_events_company_published_occurred_idx');
        });

        Schema::table('report_exports', function (Blueprint $table) {
            $table->dropIndex('report_exports_company_status_failed_at_idx');
            $table->dropIndex('report_exports_status_failed_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_company_user_created_idx');
            $table->dropIndex('audit_logs_company_action_created_idx');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_notifiable_read_created_idx');
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropIndex('failed_jobs_failed_at_idx');
            $table->dropIndex('failed_jobs_queue_failed_at_idx');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('jobs_queue_available_idx');
            $table->dropIndex('jobs_queue_reserved_available_idx');
        });
    }
};
