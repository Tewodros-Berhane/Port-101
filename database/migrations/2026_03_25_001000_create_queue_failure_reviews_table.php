<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_failure_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('failed_job_id')->unique();
            $table->string('queue', 160);
            $table->string('connection', 120);
            $table->string('job_name')->nullable();
            $table->string('job_name_label')->nullable();
            $table->string('request_id')->nullable();
            $table->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->string('classification', 40);
            $table->string('recommended_action', 40);
            $table->string('decision', 60);
            $table->text('notes')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['decision', 'reviewed_at'], 'queue_failure_reviews_decision_reviewed_idx');
            $table->index(['company_id', 'decision', 'reviewed_at'], 'queue_failure_reviews_company_decision_reviewed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_failure_reviews');
    }
};
