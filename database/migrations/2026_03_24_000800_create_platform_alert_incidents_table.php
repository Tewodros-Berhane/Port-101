<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_alert_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('alert_key', 80);
            $table->string('status', 16);
            $table->string('severity', 16);
            $table->string('title', 160);
            $table->text('message');
            $table->unsignedInteger('metric_value')->nullable();
            $table->unsignedInteger('threshold_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('first_triggered_at');
            $table->timestamp('last_triggered_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['alert_key', 'status']);
            $table->index('last_triggered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_alert_incidents');
    }
};
