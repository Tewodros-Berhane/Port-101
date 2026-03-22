<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_billables', function (Blueprint $table) {
            $table->foreignUuid('rejected_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('rejected_at')
                ->nullable()
                ->after('rejected_by');
            $table->text('rejection_reason')
                ->nullable()
                ->after('rejected_at');
            $table->foreignUuid('cancelled_by')
                ->nullable()
                ->after('rejection_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cancelled_at')
                ->nullable()
                ->after('cancelled_by');
            $table->text('cancellation_reason')
                ->nullable()
                ->after('cancelled_at');

            $table->index('rejected_by');
            $table->index('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('project_billables', function (Blueprint $table) {
            $table->dropIndex(['rejected_by']);
            $table->dropIndex(['cancelled_by']);
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'rejected_at',
                'rejection_reason',
            ]);
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
