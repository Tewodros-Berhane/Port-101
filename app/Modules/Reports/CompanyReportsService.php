<?php

namespace App\Modules\Reports;

use App\Core\Company\Models\Company;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRfq;
use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Carbon\CarbonImmutable;

class CompanyReportsService
{
    public const REPORT_SALES_PERFORMANCE = 'sales-performance';

    public const REPORT_INVENTORY_OPERATIONS = 'inventory-operations';

    public const REPORT_PURCHASING_PERFORMANCE = 'purchasing-performance';

    public const REPORT_FINANCE_SNAPSHOT = 'finance-snapshot';

    public const REPORT_APPROVAL_GOVERNANCE = 'approval-governance';

    /**
     * @var array<int, string>
     */
    public const REPORT_KEYS = [
        self::REPORT_SALES_PERFORMANCE,
        self::REPORT_INVENTORY_OPERATIONS,
        self::REPORT_PURCHASING_PERFORMANCE,
        self::REPORT_FINANCE_SNAPSHOT,
        self::REPORT_APPROVAL_GOVERNANCE,
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{key: string, title: string, description: string, row_count: int}>
     */
    public function reportCatalog(Company $company, array $filters): array
    {
        return [
            [
                'key' => self::REPORT_SALES_PERFORMANCE,
                'title' => 'Sales performance',
                'description' => 'Lead, quote, and order pipeline conversion and value tracking.',
                'row_count' => count($this->salesPerformanceRows($company, $filters)),
            ],
            [
                'key' => self::REPORT_INVENTORY_OPERATIONS,
                'title' => 'Inventory operations',
                'description' => 'Move throughput, fulfillment flow, and stock position overview.',
                'row_count' => count($this->inventoryOperationsRows($company, $filters)),
            ],
            [
                'key' => self::REPORT_PURCHASING_PERFORMANCE,
                'title' => 'Purchasing performance',
                'description' => 'RFQ and PO velocity, commitments, and receiving completion.',
                'row_count' => count($this->purchasingPerformanceRows($company, $filters)),
            ],
            [
                'key' => self::REPORT_FINANCE_SNAPSHOT,
                'title' => 'Finance snapshot',
                'description' => 'AR/AP exposure, payment coverage, and posting health metrics.',
                'row_count' => count($this->financeSnapshotRows($company, $filters)),
            ],
            [
                'key' => self::REPORT_APPROVAL_GOVERNANCE,
                'title' => 'Approval governance',
                'description' => 'Approval volumes, decision outcomes, and turnaround performance.',
                'row_count' => count($this->approvalGovernanceRows($company, $filters)),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{key: string, title: string, subtitle: string, columns: array<int, string>, rows: array<int, array<int, string|int|float>>}|null
     */
    public function buildReport(
        Company $company,
        string $reportKey,
        array $filters,
    ): ?array {
        return match ($reportKey) {
            self::REPORT_SALES_PERFORMANCE => [
                'key' => self::REPORT_SALES_PERFORMANCE,
                'title' => 'Sales Performance Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Metric', 'Value'],
                'rows' => $this->salesPerformanceRows($company, $filters),
            ],
            self::REPORT_INVENTORY_OPERATIONS => [
                'key' => self::REPORT_INVENTORY_OPERATIONS,
                'title' => 'Inventory Operations Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Metric', 'Value'],
                'rows' => $this->inventoryOperationsRows($company, $filters),
            ],
            self::REPORT_PURCHASING_PERFORMANCE => [
                'key' => self::REPORT_PURCHASING_PERFORMANCE,
                'title' => 'Purchasing Performance Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Metric', 'Value'],
                'rows' => $this->purchasingPerformanceRows($company, $filters),
            ],
            self::REPORT_FINANCE_SNAPSHOT => [
                'key' => self::REPORT_FINANCE_SNAPSHOT,
                'title' => 'Finance Snapshot Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Metric', 'Value'],
                'rows' => $this->financeSnapshotRows($company, $filters),
            ],
            self::REPORT_APPROVAL_GOVERNANCE => [
                'key' => self::REPORT_APPROVAL_GOVERNANCE,
                'title' => 'Approval Governance Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Metric', 'Value'],
                'rows' => $this->approvalGovernanceRows($company, $filters),
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dateRange(array $filters): array
    {
        $trendWindow = (int) ($filters['trend_window'] ?? 30);

        if (! in_array($trendWindow, [7, 30, 90], true)) {
            $trendWindow = 30;
        }

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($trendWindow - 1)->startOfDay();

        if (! empty($filters['start_date'])) {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['start_date'])
                    ->startOfDay();
            } catch (\Throwable) {
                // Keep default range.
            }
        }

        if (! empty($filters['end_date'])) {
            try {
                $end = CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['end_date'])
                    ->endOfDay();
            } catch (\Throwable) {
                // Keep default range.
            }
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function salesPerformanceRows(Company $company, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        $leads = SalesLead::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$start, $end]);

        $quotes = SalesQuote::query()
            ->where('company_id', $company->id)
            ->whereBetween('quote_date', [$start->toDateString(), $end->toDateString()]);

        $orders = SalesOrder::query()
            ->where('company_id', $company->id)
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()]);

        $quoteCount = (clone $quotes)->count();
        $confirmedQuotes = (clone $quotes)
            ->where('status', SalesQuote::STATUS_CONFIRMED)
            ->count();
        $conversionRate = $quoteCount > 0
            ? round(($confirmedQuotes / $quoteCount) * 100, 2)
            : 0.0;

        return [
            ['Leads created', (clone $leads)->count()],
            ['Leads won', (clone $leads)->where('stage', 'won')->count()],
            ['Quotes sent', (clone $quotes)->where('status', SalesQuote::STATUS_SENT)->count()],
            ['Quotes approved', (clone $quotes)->where('status', SalesQuote::STATUS_APPROVED)->count()],
            ['Quotes confirmed', $confirmedQuotes],
            ['Sales orders confirmed', (clone $orders)->where('status', SalesOrder::STATUS_CONFIRMED)->count()],
            ['Pipeline value', round((float) (clone $quotes)
                ->whereIn('status', [
                    SalesQuote::STATUS_DRAFT,
                    SalesQuote::STATUS_SENT,
                    SalesQuote::STATUS_APPROVED,
                ])
                ->sum('grand_total'), 2)],
            ['Booked order value', round((float) (clone $orders)
                ->whereIn('status', [
                    SalesOrder::STATUS_CONFIRMED,
                    SalesOrder::STATUS_FULFILLED,
                    SalesOrder::STATUS_INVOICED,
                    SalesOrder::STATUS_CLOSED,
                ])
                ->sum('grand_total'), 2)],
            ['Quote conversion rate %', $conversionRate],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function inventoryOperationsRows(Company $company, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        $moves = InventoryStockMove::query()
            ->where('company_id', $company->id)
            ->whereBetween('created_at', [$start, $end]);

        $stockLevels = InventoryStockLevel::query()
            ->where('company_id', $company->id);

        $doneMoves = (clone $moves)
            ->where('status', InventoryStockMove::STATUS_DONE);

        return [
            ['Stock moves (created)', (clone $moves)->count()],
            ['Open moves', (clone $moves)
                ->whereIn('status', [
                    InventoryStockMove::STATUS_DRAFT,
                    InventoryStockMove::STATUS_RESERVED,
                ])->count()],
            ['Completed moves', (clone $doneMoves)->count()],
            ['Cancelled moves', (clone $moves)
                ->where('status', InventoryStockMove::STATUS_CANCELLED)
                ->count()],
            ['Receipts completed', (clone $doneMoves)
                ->where('move_type', InventoryStockMove::TYPE_RECEIPT)
                ->count()],
            ['Deliveries completed', (clone $doneMoves)
                ->where('move_type', InventoryStockMove::TYPE_DELIVERY)
                ->count()],
            ['Transfers completed', (clone $doneMoves)
                ->where('move_type', InventoryStockMove::TYPE_TRANSFER)
                ->count()],
            ['On-hand quantity', round((float) (clone $stockLevels)->sum('on_hand_quantity'), 4)],
            ['Reserved quantity', round((float) (clone $stockLevels)->sum('reserved_quantity'), 4)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function purchasingPerformanceRows(Company $company, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        $rfqs = PurchaseRfq::query()
            ->where('company_id', $company->id)
            ->whereBetween('rfq_date', [$start->toDateString(), $end->toDateString()]);

        $orders = PurchaseOrder::query()
            ->where('company_id', $company->id)
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()]);

        return [
            ['RFQs created', (clone $rfqs)->count()],
            ['RFQs sent', (clone $rfqs)->where('status', PurchaseRfq::STATUS_SENT)->count()],
            ['RFQs selected', (clone $rfqs)->where('status', PurchaseRfq::STATUS_SELECTED)->count()],
            ['Purchase orders drafted', (clone $orders)->where('status', PurchaseOrder::STATUS_DRAFT)->count()],
            ['Purchase orders approved', (clone $orders)
                ->whereIn('status', [
                    PurchaseOrder::STATUS_APPROVED,
                    PurchaseOrder::STATUS_ORDERED,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    PurchaseOrder::STATUS_RECEIVED,
                    PurchaseOrder::STATUS_BILLED,
                    PurchaseOrder::STATUS_CLOSED,
                ])
                ->count()],
            ['Purchase orders ordered', (clone $orders)->where('status', PurchaseOrder::STATUS_ORDERED)->count()],
            ['Purchase orders received/billed', (clone $orders)
                ->whereIn('status', [
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    PurchaseOrder::STATUS_RECEIVED,
                    PurchaseOrder::STATUS_BILLED,
                    PurchaseOrder::STATUS_CLOSED,
                ])
                ->count()],
            ['Open commitments', round((float) (clone $orders)
                ->whereIn('status', [
                    PurchaseOrder::STATUS_DRAFT,
                    PurchaseOrder::STATUS_APPROVED,
                    PurchaseOrder::STATUS_ORDERED,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                ])
                ->sum('grand_total'), 2)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function financeSnapshotRows(Company $company, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        $invoices = AccountingInvoice::query()
            ->where('company_id', $company->id)
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()]);

        $payments = AccountingPayment::query()
            ->where('company_id', $company->id)
            ->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()]);

        $arOpen = (clone $invoices)
            ->where('document_type', AccountingInvoice::TYPE_CUSTOMER_INVOICE)
            ->whereIn('status', [
                AccountingInvoice::STATUS_POSTED,
                AccountingInvoice::STATUS_PARTIALLY_PAID,
            ])
            ->sum('balance_due');

        $apOpen = (clone $invoices)
            ->where('document_type', AccountingInvoice::TYPE_VENDOR_BILL)
            ->whereIn('status', [
                AccountingInvoice::STATUS_POSTED,
                AccountingInvoice::STATUS_PARTIALLY_PAID,
            ])
            ->sum('balance_due');

        return [
            ['Invoices drafted', (clone $invoices)->where('status', AccountingInvoice::STATUS_DRAFT)->count()],
            ['Invoices posted', (clone $invoices)
                ->whereIn('status', [
                    AccountingInvoice::STATUS_POSTED,
                    AccountingInvoice::STATUS_PARTIALLY_PAID,
                    AccountingInvoice::STATUS_PAID,
                ])
                ->count()],
            ['Overdue invoices', (clone $invoices)
                ->whereIn('status', [
                    AccountingInvoice::STATUS_POSTED,
                    AccountingInvoice::STATUS_PARTIALLY_PAID,
                ])
                ->whereDate('due_date', '<', now()->toDateString())
                ->where('balance_due', '>', 0)
                ->count()],
            ['Open AR balance', round((float) $arOpen, 2)],
            ['Open AP balance', round((float) $apOpen, 2)],
            ['Payments posted', (clone $payments)
                ->whereIn('status', [
                    AccountingPayment::STATUS_POSTED,
                    AccountingPayment::STATUS_RECONCILED,
                ])
                ->count()],
            ['Payments reconciled', (clone $payments)
                ->where('status', AccountingPayment::STATUS_RECONCILED)
                ->count()],
            ['Cash out/in amount', round((float) (clone $payments)
                ->whereIn('status', [
                    AccountingPayment::STATUS_POSTED,
                    AccountingPayment::STATUS_RECONCILED,
                ])
                ->sum('amount'), 2)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function approvalGovernanceRows(Company $company, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        $requests = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->whereBetween('requested_at', [$start, $end]);

        $statusFilter = $filters['approval_status'] ?? null;

        if (is_string($statusFilter) && trim($statusFilter) !== '') {
            $requests->where('status', $statusFilter);
        }

        $baseRows = (clone $requests)
            ->get(['status', 'requested_at', 'approved_at', 'rejected_at']);

        $approved = $baseRows->where('status', ApprovalRequest::STATUS_APPROVED)->count();
        $rejected = $baseRows->where('status', ApprovalRequest::STATUS_REJECTED)->count();
        $pending = $baseRows->where('status', ApprovalRequest::STATUS_PENDING)->count();
        $cancelled = $baseRows->where('status', ApprovalRequest::STATUS_CANCELLED)->count();
        $closed = $approved + $rejected;
        $approvalRate = $closed > 0
            ? round(($approved / $closed) * 100, 2)
            : 0.0;

        $turnaroundDurations = $baseRows
            ->map(function (ApprovalRequest $request): ?float {
                $decisionAt = $request->approved_at ?? $request->rejected_at;

                if (! $request->requested_at || ! $decisionAt) {
                    return null;
                }

                return round($request->requested_at->diffInSeconds($decisionAt) / 3600, 2);
            })
            ->filter(fn ($value) => $value !== null)
            ->values();

        $avgTurnaround = $turnaroundDurations->isNotEmpty()
            ? round((float) $turnaroundDurations->avg(), 2)
            : 0.0;

        $moduleBreakdown = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->whereBetween('requested_at', [$start, $end])
            ->selectRaw('module, count(*) as total')
            ->groupBy('module')
            ->pluck('total', 'module');

        return [
            ['Pending requests', $pending],
            ['Approved requests', $approved],
            ['Rejected requests', $rejected],
            ['Cancelled requests', $cancelled],
            ['Approval rate %', $approvalRate],
            ['Avg turnaround (hours)', $avgTurnaround],
            ['Sales approvals', (int) ($moduleBreakdown['sales'] ?? 0)],
            ['Purchasing approvals', (int) ($moduleBreakdown['purchasing'] ?? 0)],
            ['Inventory approvals', (int) ($moduleBreakdown['inventory'] ?? 0)],
            ['Accounting approvals', (int) ($moduleBreakdown['accounting'] ?? 0)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function subtitle(array $filters): string
    {
        [$start, $end] = $this->dateRange($filters);

        $parts = [
            'Window: '.$start->toDateString().' to '.$end->toDateString(),
        ];

        if (! empty($filters['approval_status'])) {
            $parts[] = 'Approval status: '.(string) $filters['approval_status'];
        }

        return implode(' | ', $parts);
    }
}
