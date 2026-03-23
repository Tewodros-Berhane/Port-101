<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('target_url', 2048);
            $table->text('signing_secret');
            $table->string('api_version', 16)->default('v1');
            $table->boolean('is_active')->default(true);
            $table->json('subscribed_events');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 160)->index();
            $table->string('aggregate_type', 160);
            $table->uuid('aggregate_id')->nullable();
            $table->timestamp('occurred_at');
            $table->json('payload');
            $table->timestamp('published_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'event_type', 'created_at']);
            $table->index(['company_id', 'aggregate_type', 'aggregate_id']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('integration_event_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 160)->index();
            $table->string('status', 32)->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->unsignedInteger('response_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('response_body_excerpt')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['webhook_endpoint_id', 'integration_event_id']);
            $table->index(['company_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('webhook_endpoints');
    }
};
