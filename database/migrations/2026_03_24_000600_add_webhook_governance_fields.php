<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->unsignedInteger('signing_secret_version')
                ->default(1)
                ->after('signing_secret');
            $table->timestamp('secret_rotated_at')
                ->nullable()
                ->after('subscribed_events');
            $table->timestamp('last_delivery_at')
                ->nullable()
                ->after('last_failure_at');
            $table->unsignedInteger('consecutive_failure_count')
                ->default(0)
                ->after('last_delivery_at');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->timestamp('first_attempt_at')
                ->nullable()
                ->after('attempt_count');
            $table->timestamp('dead_lettered_at')
                ->nullable()
                ->after('delivered_at');
        });

        Schema::create('webhook_secret_rotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('secret_version');
            $table->string('reason', 32)->default('manual');
            $table->string('previous_secret_preview', 32)->nullable();
            $table->string('previous_secret_fingerprint', 64)->nullable();
            $table->string('current_secret_preview', 32);
            $table->string('current_secret_fingerprint', 64);
            $table->timestamp('rotated_at');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'webhook_endpoint_id', 'rotated_at']);
            $table->unique(['webhook_endpoint_id', 'secret_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_secret_rotations');

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'first_attempt_at',
                'dead_lettered_at',
            ]);
        });

        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->dropColumn([
                'signing_secret_version',
                'secret_rotated_at',
                'last_delivery_at',
                'consecutive_failure_count',
            ]);
        });
    }
};
