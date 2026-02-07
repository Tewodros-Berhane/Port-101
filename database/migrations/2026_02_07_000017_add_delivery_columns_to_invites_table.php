<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->string('delivery_status', 20)->default('pending')->after('accepted_at');
            $table->unsignedInteger('delivery_attempts')->default(0)->after('delivery_status');
            $table->timestamp('last_delivery_at')->nullable()->after('delivery_attempts');
            $table->text('last_delivery_error')->nullable()->after('last_delivery_at');

            $table->index('delivery_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropIndex(['delivery_status']);
            $table->dropColumn([
                'delivery_status',
                'delivery_attempts',
                'last_delivery_at',
                'last_delivery_error',
            ]);
        });
    }
};
