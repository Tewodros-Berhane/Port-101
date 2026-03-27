<?php

use App\Core\Attachments\Models\Attachment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('security_context', 40)
                ->default('default')
                ->after('attachable_id');
            $table->string('scan_status', 20)
                ->default(Attachment::SCAN_CLEAN)
                ->after('checksum');
            $table->text('scan_message')
                ->nullable()
                ->after('scan_status');
            $table->timestamp('scanned_at')
                ->nullable()
                ->after('scan_message');
            $table->timestamp('quarantined_at')
                ->nullable()
                ->after('scanned_at');

            $table->index(['company_id', 'scan_status'], 'attachments_company_scan_status_idx');
            $table->index(['security_context', 'scan_status'], 'attachments_context_scan_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex('attachments_company_scan_status_idx');
            $table->dropIndex('attachments_context_scan_status_idx');
            $table->dropColumn([
                'security_context',
                'scan_status',
                'scan_message',
                'scanned_at',
                'quarantined_at',
            ]);
        });
    }
};
