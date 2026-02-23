<?php

namespace App\Http\Controllers\Company;

use App\Core\Access\Models\Invite;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\CompanyUser;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Contact;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $company = $user?->currentCompany;

        abort_unless($company, 403, 'Company context not available.');

        $companyId = $company->id;
        $today = CarbonImmutable::now()->startOfDay();
        $trendStart = $today->subDays(13);
        $membership = CompanyUser::query()
            ->with('role:id,slug,name')
            ->where('company_id', $companyId)
            ->where('user_id', $user?->id)
            ->first();

        $teamMembers = CompanyUser::query()
            ->where('company_id', $companyId)
            ->count();

        $ownerCount = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('is_owner', true)
            ->count();

        $pendingInvites = Invite::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->count();

        $failedInviteDeliveries = Invite::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->where('delivery_status', Invite::DELIVERY_FAILED)
            ->count();

        $partners = Partner::query()->where('company_id', $companyId)->count();
        $contacts = Contact::query()->where('company_id', $companyId)->count();
        $addresses = Address::query()->where('company_id', $companyId)->count();
        $products = Product::query()->where('company_id', $companyId)->count();
        $taxes = Tax::query()->where('company_id', $companyId)->count();
        $currencies = Currency::query()->where('company_id', $companyId)->count();
        $uoms = Uom::query()->where('company_id', $companyId)->count();
        $priceLists = PriceList::query()->where('company_id', $companyId)->count();
        $masterDataRecords = $partners + $contacts + $addresses + $products + $taxes + $currencies + $uoms + $priceLists;

        $activityEventsCurrentWindow = AuditLog::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                $today->subDays(6)->startOfDay(),
                $today->endOfDay(),
            ])
            ->count();

        $activityEventsPreviousWindow = AuditLog::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                $today->subDays(13)->startOfDay(),
                $today->subDays(7)->endOfDay(),
            ])
            ->count();

        $invitesCreatedCurrentWindow = Invite::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                $today->subDays(6)->startOfDay(),
                $today->endOfDay(),
            ])
            ->count();

        $invitesCreatedPreviousWindow = Invite::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [
                $today->subDays(13)->startOfDay(),
                $today->subDays(7)->endOfDay(),
            ])
            ->count();

        $recentActivity = AuditLog::query()
            ->with('actor:id,name')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'record_type' => class_basename($log->auditable_type),
                    'actor' => $log->actor?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('company/dashboard', [
            'companySummary' => [
                'name' => $company->name,
                'timezone' => $company->timezone,
                'currency_code' => $company->currency_code,
            ],
            'kpis' => [
                'team_members' => $teamMembers,
                'owners' => $ownerCount,
                'pending_invites' => $pendingInvites,
                'failed_invite_deliveries' => $failedInviteDeliveries,
                'master_data_records' => $masterDataRecords,
                'activity_events_7d' => $activityEventsCurrentWindow,
                'activity_events_change_pct' => $this->calculateChangePct(
                    $activityEventsCurrentWindow,
                    $activityEventsPreviousWindow
                ),
                'invites_created_7d' => $invitesCreatedCurrentWindow,
                'invites_created_change_pct' => $this->calculateChangePct(
                    $invitesCreatedCurrentWindow,
                    $invitesCreatedPreviousWindow
                ),
            ],
            'activityTrend' => $this->buildActivityTrend(
                $companyId,
                $trendStart,
                $today
            ),
            'inviteStatusMix' => $this->inviteStatusMix($companyId),
            'masterDataBreakdown' => [
                ['label' => 'Partners', 'value' => $partners],
                ['label' => 'Contacts', 'value' => $contacts],
                ['label' => 'Addresses', 'value' => $addresses],
                ['label' => 'Products', 'value' => $products],
                ['label' => 'Taxes', 'value' => $taxes],
                ['label' => 'Currencies', 'value' => $currencies],
                ['label' => 'Units', 'value' => $uoms],
                ['label' => 'Price lists', 'value' => $priceLists],
            ],
            'roleDashboard' => $this->buildRoleDashboard(
                $request->user(),
                $membership,
                $company->currency_code
            ),
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * @return array{
     *     variant: string,
     *     role_slug: string,
     *     role_name: string,
     *     title: string,
     *     summary: string,
     *     kpis: array<int, array{label: string, value: int|float, format: string}>,
     *     focus: array<int, array{label: string, value: int|float, format: string}>,
     *     quick_actions: array<int, array{title: string, description: string, href: string, permission: string}>
     * }
     */
    private function buildRoleDashboard(
        ?User $user,
        ?CompanyUser $membership,
        ?string $currencyCode
    ): array {
        $variant = $this->determineRoleDashboardVariant($membership);
        $roleSlug = $membership?->is_owner
            ? 'owner'
            : ($membership?->role?->slug ?? 'member');
        $roleName = $membership?->is_owner
            ? 'Owner'
            : ($membership?->role?->name ?? 'Member');

        if (! $user || $variant === 'owner') {
            return [
                'variant' => 'owner',
                'role_slug' => $roleSlug,
                'role_name' => $roleName,
                'title' => 'Owner overview',
                'summary' => 'Default command-center view across company operations.',
                'kpis' => [],
                'focus' => [],
                'quick_actions' => [],
            ];
        }

        return match ($variant) {
            'sales' => $this->buildSalesRoleDashboard($user, $roleSlug, $roleName),
            'inventory' => $this->buildInventoryRoleDashboard($user, $roleSlug, $roleName),
            'finance' => $this->buildFinanceRoleDashboard(
                $user,
                $roleSlug,
                $roleName,
                $currencyCode
            ),
            default => [
                'variant' => 'owner',
                'role_slug' => $roleSlug,
                'role_name' => $roleName,
                'title' => 'Owner overview',
                'summary' => 'Default command-center view across company operations.',
                'kpis' => [],
                'focus' => [],
                'quick_actions' => [],
            ],
        };
    }

    private function determineRoleDashboardVariant(?CompanyUser $membership): string
    {
        if (! $membership || $membership->is_owner) {
            return 'owner';
        }

        $slug = (string) ($membership->role?->slug ?? '');

        if (in_array($slug, ['sales_manager', 'sales_user'], true)) {
            return 'sales';
        }

        if (in_array($slug, ['inventory_manager', 'warehouse_clerk'], true)) {
            return 'inventory';
        }

        if (in_array($slug, ['finance_manager', 'accountant'], true)) {
            return 'finance';
        }

        return 'owner';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSalesRoleDashboard(
        User $user,
        string $roleSlug,
        string $roleName
    ): array {
        $leadQuery = $this->scopedQuery(SalesLead::query(), $user);
        $quoteQuery = $this->scopedQuery(SalesQuote::query(), $user);
        $orderQuery = $this->scopedQuery(SalesOrder::query(), $user);

        $openLeads = (clone $leadQuery)
            ->whereIn('stage', ['new', 'qualified', 'quoted'])
            ->count();
        $openQuotes = (clone $quoteQuery)
            ->whereIn('status', [
                SalesQuote::STATUS_DRAFT,
                SalesQuote::STATUS_SENT,
                SalesQuote::STATUS_APPROVED,
            ])
            ->count();
        $openOrders = (clone $orderQuery)
            ->whereIn('status', [
                SalesOrder::STATUS_DRAFT,
                SalesOrder::STATUS_CONFIRMED,
            ])
            ->count();
        $pipelineValue = round((float) (clone $quoteQuery)
            ->whereIn('status', [
                SalesQuote::STATUS_DRAFT,
                SalesQuote::STATUS_SENT,
                SalesQuote::STATUS_APPROVED,
            ])
            ->sum('grand_total'), 2);
        $quotesAwaitingApproval = (clone $quoteQuery)
            ->where('requires_approval', true)
            ->where('status', SalesQuote::STATUS_SENT)
            ->count();
        $ordersAwaitingApproval = (clone $orderQuery)
            ->where('requires_approval', true)
            ->where('status', SalesOrder::STATUS_DRAFT)
            ->count();
        $confirmedOrders30d = (clone $orderQuery)
            ->where('status', SalesOrder::STATUS_CONFIRMED)
            ->where('confirmed_at', '>=', now()->subDays(30))
            ->count();

        return [
            'variant' => 'sales',
            'role_slug' => $roleSlug,
            'role_name' => $roleName,
            'title' => 'Sales cockpit',
            'summary' => 'Pipeline and approval signals prioritized for sales execution.',
            'kpis' => [
                ['label' => 'Open leads', 'value' => $openLeads, 'format' => 'number'],
                ['label' => 'Open quotes', 'value' => $openQuotes, 'format' => 'number'],
                ['label' => 'Open orders', 'value' => $openOrders, 'format' => 'number'],
                ['label' => 'Pipeline value', 'value' => $pipelineValue, 'format' => 'currency'],
            ],
            'focus' => [
                ['label' => 'Quotes awaiting approval', 'value' => $quotesAwaitingApproval, 'format' => 'number'],
                ['label' => 'Orders awaiting approval', 'value' => $ordersAwaitingApproval, 'format' => 'number'],
                ['label' => 'Confirmed orders (30d)', 'value' => $confirmedOrders30d, 'format' => 'number'],
            ],
            'quick_actions' => [
                [
                    'title' => 'New lead',
                    'description' => 'Capture a new opportunity and start qualification.',
                    'href' => '/company/sales/leads/create',
                    'permission' => 'sales.leads.manage',
                ],
                [
                    'title' => 'New quote',
                    'description' => 'Draft a customer quote from current pipeline demand.',
                    'href' => '/company/sales/quotes/create',
                    'permission' => 'sales.quotes.manage',
                ],
                [
                    'title' => 'Open orders',
                    'description' => 'Review order confirmations and fulfillment handoffs.',
                    'href' => '/company/sales/orders',
                    'permission' => 'sales.orders.view',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInventoryRoleDashboard(
        User $user,
        string $roleSlug,
        string $roleName
    ): array {
        $stockLevelQuery = $this->scopedQuery(InventoryStockLevel::query(), $user);
        $stockMoveQuery = $this->scopedQuery(InventoryStockMove::query(), $user);

        $stockRows = (clone $stockLevelQuery)->count();
        $draftMoves = (clone $stockMoveQuery)
            ->where('status', InventoryStockMove::STATUS_DRAFT)
            ->count();
        $reservedMoves = (clone $stockMoveQuery)
            ->where('status', InventoryStockMove::STATUS_RESERVED)
            ->count();
        $lowStockAlerts = (clone $stockLevelQuery)
            ->whereRaw('on_hand_quantity <= reserved_quantity OR on_hand_quantity <= 5')
            ->count();
        $doneMoves7d = (clone $stockMoveQuery)
            ->where('status', InventoryStockMove::STATUS_DONE)
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();
        $deliveries7d = (clone $stockMoveQuery)
            ->where('status', InventoryStockMove::STATUS_DONE)
            ->where('move_type', InventoryStockMove::TYPE_DELIVERY)
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();
        $receipts7d = (clone $stockMoveQuery)
            ->where('status', InventoryStockMove::STATUS_DONE)
            ->where('move_type', InventoryStockMove::TYPE_RECEIPT)
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();

        return [
            'variant' => 'inventory',
            'role_slug' => $roleSlug,
            'role_name' => $roleName,
            'title' => 'Inventory control board',
            'summary' => 'Stock-risk and move-lifecycle signals for warehouse operations.',
            'kpis' => [
                ['label' => 'Stock rows', 'value' => $stockRows, 'format' => 'number'],
                ['label' => 'Draft moves', 'value' => $draftMoves, 'format' => 'number'],
                ['label' => 'Reserved moves', 'value' => $reservedMoves, 'format' => 'number'],
                ['label' => 'Low-stock alerts', 'value' => $lowStockAlerts, 'format' => 'number'],
            ],
            'focus' => [
                ['label' => 'Completed moves (7d)', 'value' => $doneMoves7d, 'format' => 'number'],
                ['label' => 'Deliveries completed (7d)', 'value' => $deliveries7d, 'format' => 'number'],
                ['label' => 'Receipts completed (7d)', 'value' => $receipts7d, 'format' => 'number'],
            ],
            'quick_actions' => [
                [
                    'title' => 'New stock move',
                    'description' => 'Create a receipt, delivery, transfer, or adjustment move.',
                    'href' => '/company/inventory/moves/create',
                    'permission' => 'inventory.moves.manage',
                ],
                [
                    'title' => 'Stock levels',
                    'description' => 'Inspect current on-hand, reserved, and available quantities.',
                    'href' => '/company/inventory/stock-levels',
                    'permission' => 'inventory.stock.view',
                ],
                [
                    'title' => 'Warehouses',
                    'description' => 'Maintain warehouse setup and operational locations.',
                    'href' => '/company/inventory/warehouses',
                    'permission' => 'inventory.stock.view',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFinanceRoleDashboard(
        User $user,
        string $roleSlug,
        string $roleName,
        ?string $currencyCode
    ): array {
        $invoiceQuery = $this->scopedQuery(AccountingInvoice::query(), $user);
        $paymentQuery = $this->scopedQuery(AccountingPayment::query(), $user);

        $draftInvoices = (clone $invoiceQuery)
            ->where('status', AccountingInvoice::STATUS_DRAFT)
            ->count();
        $postedInvoices = (clone $invoiceQuery)
            ->whereIn('status', [
                AccountingInvoice::STATUS_POSTED,
                AccountingInvoice::STATUS_PARTIALLY_PAID,
            ])
            ->count();
        $overdueInvoices = (clone $invoiceQuery)
            ->whereIn('status', [
                AccountingInvoice::STATUS_POSTED,
                AccountingInvoice::STATUS_PARTIALLY_PAID,
            ])
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('balance_due', '>', 0)
            ->count();
        $openReceivables = round((float) (clone $invoiceQuery)
            ->where('document_type', AccountingInvoice::TYPE_CUSTOMER_INVOICE)
            ->whereIn('status', [
                AccountingInvoice::STATUS_POSTED,
                AccountingInvoice::STATUS_PARTIALLY_PAID,
            ])
            ->sum('balance_due'), 2);
        $postedPayments30d = (clone $paymentQuery)
            ->whereIn('status', [
                AccountingPayment::STATUS_POSTED,
                AccountingPayment::STATUS_RECONCILED,
            ])
            ->whereDate('payment_date', '>=', now()->subDays(30)->toDateString())
            ->count();
        $reconciledPayments30d = (clone $paymentQuery)
            ->where('status', AccountingPayment::STATUS_RECONCILED)
            ->where('reconciled_at', '>=', now()->subDays(30))
            ->count();
        $vendorBillsPendingPosting = (clone $invoiceQuery)
            ->where('document_type', AccountingInvoice::TYPE_VENDOR_BILL)
            ->where('status', AccountingInvoice::STATUS_DRAFT)
            ->count();
        $invoicesReadyToPost = (clone $invoiceQuery)
            ->where('status', AccountingInvoice::STATUS_DRAFT)
            ->where('delivery_status', AccountingInvoice::DELIVERY_STATUS_READY)
            ->count();

        $resolvedCurrencyCode = $currencyCode ?: 'USD';

        return [
            'variant' => 'finance',
            'role_slug' => $roleSlug,
            'role_name' => $roleName,
            'title' => 'Finance command board',
            'summary' => 'Receivables, posting, and payment health for finance teams.',
            'kpis' => [
                ['label' => 'Draft invoices', 'value' => $draftInvoices, 'format' => 'number'],
                ['label' => 'Posted invoices', 'value' => $postedInvoices, 'format' => 'number'],
                ['label' => 'Overdue invoices', 'value' => $overdueInvoices, 'format' => 'number'],
                [
                    'label' => 'Open receivables ('.$resolvedCurrencyCode.')',
                    'value' => $openReceivables,
                    'format' => 'currency',
                ],
            ],
            'focus' => [
                ['label' => 'Invoices ready to post', 'value' => $invoicesReadyToPost, 'format' => 'number'],
                ['label' => 'Pending vendor bills', 'value' => $vendorBillsPendingPosting, 'format' => 'number'],
                ['label' => 'Reconciled payments (30d)', 'value' => $reconciledPayments30d, 'format' => 'number'],
                ['label' => 'Posted payments (30d)', 'value' => $postedPayments30d, 'format' => 'number'],
            ],
            'quick_actions' => [
                [
                    'title' => 'New invoice',
                    'description' => 'Create a customer invoice or vendor bill draft.',
                    'href' => '/company/accounting/invoices/create',
                    'permission' => 'accounting.invoices.manage',
                ],
                [
                    'title' => 'New payment',
                    'description' => 'Capture and post incoming or outgoing payments.',
                    'href' => '/company/accounting/payments/create',
                    'permission' => 'accounting.payments.manage',
                ],
                [
                    'title' => 'Open accounting',
                    'description' => 'Review invoices, payments, and overdue balances.',
                    'href' => '/company/accounting',
                    'permission' => 'accounting.invoices.view',
                ],
            ],
        ];
    }

    private function scopedQuery(Builder $query, User $user): Builder
    {
        return $user->applyDataScopeToQuery($query);
    }

    private function calculateChangePct(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array<int, array{date: string, audits: int, invites: int}>
     */
    private function buildActivityTrend(
        string $companyId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate
    ): array {
        $rows = [];

        for ($day = $startDate; $day->lte($endDate); $day = $day->addDay()) {
            $rows[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'audits' => 0,
                'invites' => 0,
            ];
        }

        $auditRows = AuditLog::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('date')
            ->get();

        foreach ($auditRows as $row) {
            $date = (string) $row->date;

            if (isset($rows[$date])) {
                $rows[$date]['audits'] = (int) $row->total;
            }
        }

        $inviteRows = Invite::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->groupBy('date')
            ->get();

        foreach ($inviteRows as $row) {
            $date = (string) $row->date;

            if (isset($rows[$date])) {
                $rows[$date]['invites'] = (int) $row->total;
            }
        }

        return array_values($rows);
    }

    /**
     * @return array{pending: int, accepted: int, expired: int, total: int}
     */
    private function inviteStatusMix(string $companyId): array
    {
        $accepted = Invite::query()
            ->where('company_id', $companyId)
            ->whereNotNull('accepted_at')
            ->count();

        $expired = Invite::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        $pending = Invite::query()
            ->where('company_id', $companyId)
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->count();

        return [
            'pending' => $pending,
            'accepted' => $accepted,
            'expired' => $expired,
            'total' => $pending + $accepted + $expired,
        ];
    }
}
