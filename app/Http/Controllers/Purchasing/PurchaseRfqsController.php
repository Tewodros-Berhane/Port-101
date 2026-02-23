<?php

namespace App\Http\Controllers\Purchasing;

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseRfqStoreRequest;
use App\Http\Requests\Purchasing\PurchaseRfqUpdateRequest;
use App\Modules\Purchasing\Models\PurchaseRfq;
use App\Modules\Purchasing\PurchasingOrderWorkflowService;
use App\Modules\Purchasing\PurchasingRfqWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRfqsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseRfq::class);

        $user = $request->user();

        $query = PurchaseRfq::query()
            ->with(['partner:id,name', 'order:id,rfq_id,order_number,status'])
            ->latest('rfq_date')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $rfqs = $query->paginate(20)->withQueryString();

        return Inertia::render('purchasing/rfqs/index', [
            'rfqs' => $rfqs->through(fn (PurchaseRfq $rfq) => [
                'id' => $rfq->id,
                'rfq_number' => $rfq->rfq_number,
                'status' => $rfq->status,
                'partner_name' => $rfq->partner?->name,
                'rfq_date' => $rfq->rfq_date?->toDateString(),
                'valid_until' => $rfq->valid_until?->toDateString(),
                'grand_total' => (float) $rfq->grand_total,
                'order_number' => $rfq->order?->order_number,
                'order_status' => $rfq->order?->status,
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PurchaseRfq::class);

        return Inertia::render('purchasing/rfqs/create', [
            'rfq' => [
                'partner_id' => '',
                'rfq_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'notes' => '',
                'lines' => [[
                    'product_id' => '',
                    'description' => '',
                    'quantity' => 1,
                    'unit_cost' => 0,
                    'tax_rate' => 0,
                ]],
            ],
            'partners' => $this->vendorOptions(),
            'products' => $this->productOptions(),
        ]);
    }

    public function store(
        PurchaseRfqStoreRequest $request,
        PurchasingRfqWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('create', PurchaseRfq::class);

        $user = $request->user();
        $companyId = (string) $user?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $rfq = $workflowService->createDraft(
            attributes: $request->validated(),
            companyId: $companyId,
            actorId: $user?->id,
        );

        return redirect()
            ->route('company.purchasing.rfqs.edit', $rfq)
            ->with('success', 'RFQ created.');
    }

    public function edit(PurchaseRfq $rfq): Response
    {
        $this->authorize('view', $rfq);

        $rfq->load(['lines', 'partner:id,name', 'order:id,rfq_id,order_number,status']);

        return Inertia::render('purchasing/rfqs/edit', [
            'rfq' => [
                'id' => $rfq->id,
                'partner_id' => $rfq->partner_id,
                'partner_name' => $rfq->partner?->name,
                'rfq_number' => $rfq->rfq_number,
                'status' => $rfq->status,
                'rfq_date' => $rfq->rfq_date?->toDateString(),
                'valid_until' => $rfq->valid_until?->toDateString(),
                'subtotal' => (float) $rfq->subtotal,
                'tax_total' => (float) $rfq->tax_total,
                'grand_total' => (float) $rfq->grand_total,
                'sent_at' => $rfq->sent_at?->toIso8601String(),
                'vendor_responded_at' => $rfq->vendor_responded_at?->toIso8601String(),
                'selected_at' => $rfq->selected_at?->toIso8601String(),
                'notes' => $rfq->notes,
                'order_id' => $rfq->order?->id,
                'order_number' => $rfq->order?->order_number,
                'order_status' => $rfq->order?->status,
                'lines' => $rfq->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit_cost' => (float) $line->unit_cost,
                    'tax_rate' => (float) $line->tax_rate,
                ])->values()->all(),
            ],
            'partners' => $this->vendorOptions(),
            'products' => $this->productOptions(),
        ]);
    }

    public function update(
        PurchaseRfqUpdateRequest $request,
        PurchaseRfq $rfq,
        PurchasingRfqWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('update', $rfq);

        $workflowService->updateDraft(
            rfq: $rfq,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.purchasing.rfqs.edit', $rfq)
            ->with('success', 'RFQ updated.');
    }

    public function send(
        Request $request,
        PurchaseRfq $rfq,
        PurchasingRfqWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('send', $rfq);

        $workflowService->send($rfq, $request->user()?->id);

        return redirect()
            ->route('company.purchasing.rfqs.edit', $rfq)
            ->with('success', 'RFQ marked as sent.');
    }

    public function respond(
        Request $request,
        PurchaseRfq $rfq,
        PurchasingRfqWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('markVendorResponded', $rfq);

        $workflowService->markVendorResponded($rfq, $request->user()?->id);

        return redirect()
            ->route('company.purchasing.rfqs.edit', $rfq)
            ->with('success', 'Vendor response recorded.');
    }

    public function select(
        Request $request,
        PurchaseRfq $rfq,
        PurchasingRfqWorkflowService $rfqWorkflowService,
        PurchasingOrderWorkflowService $orderWorkflowService,
    ): RedirectResponse {
        $this->authorize('select', $rfq);

        $selectedRfq = $rfqWorkflowService->select($rfq, $request->user()?->id);
        $order = $orderWorkflowService->createOrRefreshDraftFromRfq(
            $selectedRfq,
            $request->user()?->id,
        );

        return redirect()
            ->route('company.purchasing.orders.edit', $order)
            ->with('success', 'RFQ selected and purchase order prepared.');
    }

    public function destroy(PurchaseRfq $rfq): RedirectResponse
    {
        $this->authorize('delete', $rfq);

        if ($rfq->order()->exists()) {
            return back()->with('error', 'Linked RFQs cannot be deleted once an order exists.');
        }

        $rfq->lines()->delete();
        $rfq->delete();

        return redirect()
            ->route('company.purchasing.rfqs.index')
            ->with('success', 'RFQ deleted.');
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
}
