<?php

namespace App\Core\Platform;

use App\Core\Company\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SeededIntegrationSmokeCheckService
{
    public function configSummary(?string $requestedCompanySlug = null, ?Company $company = null): array
    {
        $connection = (string) config('database.default');

        return [
            'database_connection' => $connection,
            'database_name' => (string) config("database.connections.{$connection}.database"),
            'requested_company_slug' => $requestedCompanySlug,
            'default_company_slug' => (string) config('core.integration.smoke_check_company_slug', 'demo-company-workflow'),
            'resolved_company_slug' => $company?->slug,
            'resolved_company_id' => $company?->id,
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     checks: array<int, array<string, mixed>>,
     *     config: array<string, mixed>
     * }
     */
    public function run(?string $companySlug = null): array
    {
        $resolvedCompanySlug = $companySlug ?: (string) config('core.integration.smoke_check_company_slug', 'demo-company-workflow');
        $company = Company::query()
            ->where('slug', $resolvedCompanySlug)
            ->first();

        if (! $company) {
            $company = Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->first();
        }

        $checks = [
            $this->platformAdminsCheck(),
            $this->activeCompaniesCheck(),
            $this->targetCompanyCheck($resolvedCompanySlug, $company),
            $this->masterDataCheck($company),
            $this->salesCheck($company),
            $this->purchasingCheck($company),
            $this->inventoryCheck($company),
            $this->accountingCheck($company),
            $this->approvalsCheck($company),
            $this->operationsCheck($company),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check) => (bool) ($check['ok'] ?? false)),
            'checks' => $checks,
            'config' => $this->configSummary($resolvedCompanySlug, $company),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function platformAdminsCheck(): array
    {
        $count = User::query()
            ->where('is_super_admin', true)
            ->count();

        return [
            'key' => 'platform_admins',
            'label' => 'Platform admins',
            'ok' => $count > 0,
            'detail' => $count > 0
                ? "Found {$count} platform admin account(s)."
                : 'No platform admin accounts found.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeCompaniesCheck(): array
    {
        $count = Company::query()
            ->where('is_active', true)
            ->count();

        return [
            'key' => 'active_companies',
            'label' => 'Active companies',
            'ok' => $count > 0,
            'detail' => $count > 0
                ? "Found {$count} active compan(ies)."
                : 'No active companies found.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function targetCompanyCheck(string $requestedCompanySlug, ?Company $company): array
    {
        return [
            'key' => 'target_company',
            'label' => 'Target company',
            'ok' => $company !== null,
            'detail' => $company
                ? "Using company {$company->name} [{$company->slug}]."
                : "No company found for requested slug [{$requestedCompanySlug}] and no active fallback company was available.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function masterDataCheck(?Company $company): array
    {
        return $this->companyAggregateCheck(
            key: 'master_data',
            label: 'Master data baseline',
            company: $company,
            tables: [
                'partners' => ['label' => 'partners', 'min' => 1],
                'products' => ['label' => 'products', 'min' => 1],
                'currencies' => ['label' => 'currencies', 'min' => 1],
                'taxes' => ['label' => 'taxes', 'min' => 1],
                'uoms' => ['label' => 'uoms', 'min' => 1],
                'price_lists' => ['label' => 'price lists', 'min' => 1],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function salesCheck(?Company $company): array
    {
        return $this->companyAggregateCheck(
            key: 'sales',
            label: 'Sales workflow baseline',
            company: $company,
            tables: [
                'sales_leads' => ['label' => 'leads', 'min' => 1],
                'sales_quotes' => ['label' => 'quotes', 'min' => 1],
                'sales_orders' => ['label' => 'orders', 'min' => 1],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasingCheck(?Company $company): array
    {
        return $this->companyAggregateCheck(
            key: 'purchasing',
            label: 'Purchasing workflow baseline',
            company: $company,
            tables: [
                'purchase_rfqs' => ['label' => 'rfqs', 'min' => 1],
                'purchase_orders' => ['label' => 'purchase orders', 'min' => 1],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryCheck(?Company $company): array
    {
        return $this->companyAggregateCheck(
            key: 'inventory',
            label: 'Inventory workflow baseline',
            company: $company,
            tables: [
                'inventory_warehouses' => ['label' => 'warehouses', 'min' => 1],
                'inventory_locations' => ['label' => 'locations', 'min' => 1],
                'inventory_stock_levels' => ['label' => 'stock levels', 'min' => 1],
                'inventory_stock_moves' => ['label' => 'stock moves', 'min' => 1],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function accountingCheck(?Company $company): array
    {
        return $this->companyAggregateCheck(
            key: 'accounting',
            label: 'Accounting workflow baseline',
            company: $company,
            tables: [
                'accounting_invoices' => ['label' => 'invoices', 'min' => 1],
                'accounting_payments' => ['label' => 'payments', 'min' => 1],
                'accounting_ledger_entries' => ['label' => 'ledger entries', 'min' => 1],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalsCheck(?Company $company): array
    {
        return $this->companyAggregateCheck(
            key: 'approvals',
            label: 'Approval baseline',
            company: $company,
            tables: [
                'approval_requests' => ['label' => 'approval requests', 'min' => 1],
                'approval_steps' => ['label' => 'approval steps', 'min' => 1],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function operationsCheck(?Company $company): array
    {
        if (! $company) {
            return $this->missingCompanyCheck('operations', 'Operational data baseline');
        }

        $auditLogCount = DB::table('audit_logs')
            ->where('company_id', $company->id)
            ->count();

        $settingsCount = DB::table('settings')
            ->where('company_id', $company->id)
            ->count();

        $companyUserIds = DB::table('company_users')
            ->where('company_id', $company->id)
            ->pluck('user_id');

        $notificationCount = DB::table('notifications')
            ->whereIn('notifiable_id', $companyUserIds)
            ->where('notifiable_type', User::class)
            ->count();

        $ok = $auditLogCount > 0 && $settingsCount > 0 && $notificationCount > 0;

        return [
            'key' => 'operations',
            'label' => 'Operational data baseline',
            'ok' => $ok,
            'detail' => implode(' | ', [
                "audit logs={$auditLogCount}",
                "settings={$settingsCount}",
                "notifications={$notificationCount}",
            ]),
        ];
    }

    /**
     * @param  array<string, array{label: string, min: int}>  $tables
     * @return array<string, mixed>
     */
    private function companyAggregateCheck(string $key, string $label, ?Company $company, array $tables): array
    {
        if (! $company) {
            return $this->missingCompanyCheck($key, $label);
        }

        $details = [];
        $ok = true;

        foreach ($tables as $table => $definition) {
            $count = DB::table($table)
                ->where('company_id', $company->id)
                ->count();

            $details[] = "{$definition['label']}={$count}";
            $ok = $ok && $count >= $definition['min'];
        }

        return [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'detail' => implode(' | ', $details),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function missingCompanyCheck(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'ok' => false,
            'detail' => 'Cannot evaluate without a resolved company.',
        ];
    }
}
