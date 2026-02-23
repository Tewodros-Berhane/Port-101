<?php

namespace App\Http\Controllers\Purchasing;

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseOrderReceiveRequest;
use App\Http\Requests\Purchasing\PurchaseOrderStoreRequest;
use App\Http\Requests\Purchasing\PurchaseOrderUpdateRequest;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRfq;
use App\Modules\Purchasing\PurchasingOrderWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrdersController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $user = $request->user();

        $query = PurchaseOrder::query()
            ->with(['partner:id,name', 'rfq:id,rfq_number'])
            ->latest('order_date')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $orders = $query->paginate(20)->withQueryString();

        return Inertia::render('purchasing/orders/index', [
            'orders' => $orders->through(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'partner_name' => $order->partner?->name,
                'rfq_number' => $order->rfq?->rfq_number,
                'order_date' => $order->order_date?->toDateString(),
                'grand_total' => (float) $order->grand_total,
                'requires_approval' => (bool) $order->requires_approval,
                'received_at' => $order->received_at?->toIso8601String(),
                'billed_at' => $order->billed_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        $selectedRfq = null;
        $rfqId = $request->string('rfq')->toString();
        $user = $request->user();

        if ($rfqId !== '') {
            $rfqQuery = PurchaseRfq::query()
                ->with(['lines'])
                ->where('id', $rfqId);

            if ($user) {
                $rfqQuery = $user->applyDataScopeToQuery($rfqQuery);
            }

            $selectedRfq = $rfqQuery->first();
        }

        $lines = $selectedRfq
            ? $selectedRfq->lines->map(fn ($line) => [
                'product_id' => $line->product_id ?? '',
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'tax_rate' => (float) $line->tax_rate,
            ])->values()->all()
            : [[
                'product_id' => '',
                'description' => '',
                'quantity' => 1,
                'unit_cost' => 0,
                'tax_rate' => 0,
            ]];

        return Inertia::render('purchasing/orders/create', [
            'order' => [
                'rfq_id' => $selectedRfq?->id ?? '',
                'partner_id' => $selectedRfq?->partner_id ?? '',
                'order_date' => now()->toDateString(),
                'notes' => $selectedRfq
                    ? 'Generated from RFQ '.$selectedRfq->rfq_number
                    : '',
                'lines' => $lines,
            ],
            'partners' => $this->vendorOptions(),
            'products' => $this->productOptions(),
            'rfqs' => $this->rfqOptions($request, $selectedRfq?->id),
        ]);
    }

    public function store(
        PurchaseOrderStoreRequest $request,
        PurchasingOrderWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('create', PurchaseOrder::class);

        $user = $request->user();
        $companyId = (string) $user?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $order = $workflowService->createDraft(
            attributes: $request->validated(),
            companyId: $companyId,
            actorId: $user?->id,
        );

        return redirect()
            ->route('company.purchasing.orders.edit', $order)
            ->with('success', 'Purchase order created.');
    }

    public function edit(Request $request, PurchaseOrder $order): Response
    {
        $this->authorize('view', $order);

        $order->load([
            'lines',
            'partner:id,name',
            'rfq:id,rfq_number',
            'vendorBills:id,purchase_order_id,invoice_number,document_type,status,invoice_date,balance_due',
        ]);

        return Inertia::render('purchasing/orders/edit', [
            'order' => [
                'id' => $order->id,
                'rfq_id' => $order->rfq_id,
                'rfq_number' => $order->rfq?->rfq_number,
                'partner_id' => $order->partner_id,
                'partner_name' => $order->partner?->name,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'order_date' => $order->order_date?->toDateString(),
                'subtotal' => (float) $order->subtotal,
                'tax_total' => (float) $order->tax_total,
                'grand_total' => (float) $order->grand_total,
                'requires_approval' => (bool) $order->requires_approval,
                'approved_at' => $order->approved_at?->toIso8601String(),
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'received_at' => $order->received_at?->toIso8601String(),
                'billed_at' => $order->billed_at?->toIso8601String(),
                'notes' => $order->notes,
                'lines' => $order->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'received_quantity' => (float) $line->received_quantity,
                    'unit_cost' => (float) $line->unit_cost,
                    'tax_rate' => (float) $line->tax_rate,
                ])->values()->all(),
                'vendor_bills' => $order->vendorBills
                    ->where('document_type', AccountingInvoice::TYPE_VENDOR_BILL)
                    ->sortByDesc('invoice_date')
                    ->values()
                    ->map(fn ($invoice) => [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'invoice_date' => $invoice->invoice_date?->toDateString(),
                        'balance_due' => (float) $invoice->balance_due,
                    ])
                    ->all(),
            ],
            'partners' => $this->vendorOptions(),
            'products' => $this->productOptions(),
            'rfqs' => $this->rfqOptions($request, (string) $order->rfq_id),
        ]);
    }

    public function update(
        PurchaseOrderUpdateRequest $request,
        PurchaseOrder $order,
        PurchasingOrderWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('update', $order);

        $workflowService->updateDraft(
            order: $order,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.purchasing.orders.edit', $order)
            ->with('success', 'Purchase order updated.');
    }

    public function approve(
        Request $request,
        PurchaseOrder $order,
        PurchasingOrderWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('approve', $order);

        $workflowService->approve($order, $request->user()?->id);

        return redirect()
            ->route('company.purchasing.orders.edit', $order)
            ->with('success', 'Purchase order approved.');
    }

    public function place(
        Request $request,
        PurchaseOrder $order,
        PurchasingOrderWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('place', $order);

        if (
            $order->requires_approval
            && $order->status !== PurchaseOrder::STATUS_APPROVED
        ) {
            return back()->with('error', 'This purchase order requires approval before placement.');
        }

        $workflowService->place($order, $request->user()?->id);

        return redirect()
            ->route('company.purchasing.orders.edit', $order)
            ->with('success', 'Purchase order placed.');
    }

    public function receive(
        PurchaseOrderReceiveRequest $request,
        PurchaseOrder $order,
        PurchasingOrderWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('receive', $order);

        $lineQuantities = collect($request->validated('lines', []))
            ->mapWithKeys(fn (array $line) => [
                (string) $line['line_id'] => (float) $line['quantity'],
            ])
            ->all();

        $workflowService->receive($order, $lineQuantities, $request->user()?->id);

        return redirect()
            ->route('company.purchasing.orders.edit', $order)
            ->with('success', 'Receipt captured and vendor bill handoff triggered.');
    }

    public function destroy(PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        if ($order->vendorBills()->exists()) {
            return back()->with('error', 'Order has linked vendor bills and cannot be deleted.');
        }

        $order->lines()->delete();
        $order->delete();

        return redirect()
            ->route('company.purchasing.orders.index')
            ->with('success', 'Purchase order deleted.');
    }

    /**
     * @return array<int, array{id: string, name: string, type: string}>
     */
    private function vendorOptions(): array
    {
        return Partner::query()
            ->whereIn('type', ['vendor', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'type' => $partner->type,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, sku: string|null}>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, rfq_number: string, partner_id: string, partner_name: string|null, status: string}>
     */
    private function rfqOptions(Request $request, ?string $includeRfqId = null): array
    {
        $user = $request->user();

        $query = PurchaseRfq::query()
            ->with(['partner:id,name', 'order:id,rfq_id'])
            ->orderByDesc('created_at');

        if ($user) {
            $query = $user->applyDataScopeToQuery($query);
        }

        return $query
            ->limit(200)
            ->get(['id', 'rfq_number', 'partner_id', 'status'])
            ->filter(function (PurchaseRfq $rfq) use ($includeRfqId): bool {
                if ($includeRfqId && (string) $rfq->id === (string) $includeRfqId) {
                    return true;
                }

                return ! $rfq->order;
            })
            ->values()
            ->map(fn (PurchaseRfq $rfq) => [
                'id' => $rfq->id,
                'rfq_number' => $rfq->rfq_number,
                'partner_id' => $rfq->partner_id,
                'partner_name' => $rfq->partner?->name,
                'status' => $rfq->status,
            ])
            ->all();
    }
}
