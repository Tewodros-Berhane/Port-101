<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('request_type', 16);
            $table->string('full_name', 160);
            $table->string('work_email', 255);
            $table->string('company_name', 160);
            $table->string('role_title', 160);
            $table->string('team_size', 32);
            $table->json('modules_interest')->nullable();
            $table->text('message')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('source_page', 255)->nullable();
            $table->string('status', 32)->default('new');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->timestamps();

            $table->index(['request_type', 'status']);
            $table->index('created_at');
            $table->index('work_email');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
