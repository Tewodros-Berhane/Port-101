<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'partners',
            'products',
            'sales_leads',
            'sales_quotes',
            'sales_orders',
            'purchase_rfqs',
            'purchase_orders',
            'accounting_invoices',
            'accounting_payments',
            'projects',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->string('external_reference', 191)->nullable()->after('company_id');
                $blueprint->unique(
                    ['company_id', 'external_reference'],
                    $table.'_company_external_reference_unique',
                );
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'partners',
            'products',
            'sales_leads',
            'sales_quotes',
            'sales_orders',
            'purchase_rfqs',
            'purchase_orders',
            'accounting_invoices',
            'accounting_payments',
            'projects',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropUnique($table.'_company_external_reference_unique');
                $blueprint->dropColumn('external_reference');
            });
        }
    }
};
