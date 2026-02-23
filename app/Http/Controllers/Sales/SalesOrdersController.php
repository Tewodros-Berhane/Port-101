<?php

namespace App\Http\Controllers\Sales;

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Modules\Sales\SalesApprovalPolicyService;
use App\Modules\Sales\SalesDocumentTotalsService;
use App\Modules\Sales\SalesNumberingService;
use App\Modules\Sales\SalesOrderWorkflowService;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesOrderStoreRequest;
use App\Http\Requests\Sales\SalesOrderUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrdersController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalesOrder::class);

        $user = $request->user();

        $ordersQuery = SalesOrder::query()
            ->with(['partner:id,name', 'quote:id,quote_number'])
            ->orderByDesc('created_at')
            ->when($user, fn ($query) => $user->applyDataScopeToQuery($query));

        $orders = $ordersQuery->paginate(20)->withQueryString();

        return Inertia::render('sales/orders/index', [
            'orders' => $orders->through(function (SalesOrder $order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'partner_name' => $order->partner?->name,
                    'quote_number' => $order->quote?->quote_number,
                    'order_date' => $order->order_date?->toDateString(),
                    'grand_total' => (float) $order->grand_total,
                    'requires_approval' => (bool) $order->requires_approval,
                    'approved_at' => $order->approved_at?->toIso8601String(),
                    'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', SalesOrder::class);

        $quoteId = $request->string('quote')->toString();

        return Inertia::render('sales/orders/create', [
            'order' => [
                'quote_id' => $quoteId ?: null,
                'order_date' => now()->toDateString(),
                'partner_id' => null,
                'lines' => [
                    [
                        'product_id' => null,
                        'description' => '',
                        'quantity' => 1,
                        'unit_price' => 0,
                        'discount_percent' => 0,
                        'tax_rate' => 0,
                    ],
                ],
            ],
            'partners' => $this->partnerOptions(),
            'products' => $this->productOptions(),
            'quotes' => $this->quoteOptions($request),
        ]);
    }

    public function store(
        SalesOrderStoreRequest $request,
        SalesDocumentTotalsService $totalsService,
        SalesApprovalPolicyService $approvalPolicyService,
        SalesNumberingService $numberingService
    ): RedirectResponse {
        $this->authorize('create', SalesOrder::class);

        $user = $request->user();
        $companyId = $user?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $validated = $request->validated();
        $calculated = $totalsService->calculate($validated['lines']);
        $totals = $calculated['totals'];

        $order = DB::transaction(function () use (
            $validated,
            $calculated,
            $totals,
            $approvalPolicyService,
            $numberingService,
            $companyId,
            $user
        ) {
            $order = SalesOrder::create([
                'company_id' => $companyId,
                'quote_id' => $validated['quote_id'] ?? null,
                'partner_id' => $validated['partner_id'],
                'order_number' => $numberingService->nextOrderNumber($companyId, $user?->id),
                'status' => SalesOrder::STATUS_DRAFT,
                'order_date' => $validated['order_date'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'requires_approval' => $approvalPolicyService->requiresApproval(
                    companyId: $companyId,
                    amount: $totals['grand_total'],
                ),
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            $order->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $user?->id,
                        'updated_by' => $user?->id,
                    ])
                    ->all()
            );

            if ($order->quote_id) {
                SalesQuote::query()
                    ->where('id', $order->quote_id)
                    ->update([
                        'status' => SalesQuote::STATUS_CONFIRMED,
                        'updated_by' => $user?->id,
                    ]);
            }

            return $order;
        });

        return redirect()
            ->route('company.sales.orders.edit', $order)
            ->with('success', 'Sales order created.');
    }

    public function edit(SalesOrder $order): Response
    {
        $this->authorize('update', $order);

        $order->load('lines');

        return Inertia::render('sales/orders/edit', [
            'order' => [
                'id' => $order->id,
                'quote_id' => $order->quote_id,
                'partner_id' => $order->partner_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'order_date' => $order->order_date?->toDateString(),
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'tax_total' => (float) $order->tax_total,
                'grand_total' => (float) $order->grand_total,
                'requires_approval' => (bool) $order->requires_approval,
                'approved_at' => $order->approved_at?->toIso8601String(),
                'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                'lines' => $order->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'discount_percent' => (float) $line->discount_percent,
                    'tax_rate' => (float) $line->tax_rate,
                ])->values()->all(),
            ],
            'partners' => $this->partnerOptions(),
            'products' => $this->productOptions(),
            'quotes' => $this->quoteOptions(request()),
        ]);
    }

    public function update(
        SalesOrderUpdateRequest $request,
        SalesOrder $order,
        SalesDocumentTotalsService $totalsService,
        SalesApprovalPolicyService $approvalPolicyService
    ): RedirectResponse {
        $this->authorize('update', $order);

        $validated = $request->validated();
        $user = $request->user();
        $companyId = (string) $order->company_id;

        $calculated = $totalsService->calculate($validated['lines']);
        $totals = $calculated['totals'];

        DB::transaction(function () use (
            $order,
            $validated,
            $calculated,
            $totals,
            $approvalPolicyService,
            $user,
            $companyId
        ) {
            $requiresApproval = $approvalPolicyService->requiresApproval(
                companyId: $companyId,
                amount: $totals['grand_total'],
            );

            $resetApproval = $order->approved_at !== null && $requiresApproval;

            $order->update([
                'partner_id' => $validated['partner_id'],
                'order_date' => $validated['order_date'],
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'requires_approval' => $requiresApproval,
                'approved_by' => $resetApproval ? null : $order->approved_by,
                'approved_at' => $resetApproval ? null : $order->approved_at,
                'updated_by' => $user?->id,
            ]);

            $order->lines()->delete();
            $order->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $user?->id,
                        'updated_by' => $user?->id,
                    ])
                    ->all()
            );
        });

        return redirect()
            ->route('company.sales.orders.edit', $order)
            ->with('success', 'Sales order updated.');
    }

    public function approve(Request $request, SalesOrder $order): RedirectResponse
    {
        $this->authorize('approve', $order);

        $order->update([
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.sales.orders.edit', $order)
            ->with('success', 'Sales order approved.');
    }

    public function confirm(
        Request $request,
        SalesOrder $order,
        SalesOrderWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('confirm', $order);

        if ($order->requires_approval && ! $order->approved_at) {
            return back()->with('error', 'This order requires manager approval before confirmation.');
        }

        $workflowService->confirm($order, $request->user());

        if ($order->quote_id) {
            SalesQuote::query()
                ->where('id', $order->quote_id)
                ->update([
                    'status' => SalesQuote::STATUS_CONFIRMED,
                    'updated_by' => $request->user()?->id,
                ]);
        }

        return redirect()
            ->route('company.sales.orders.edit', $order)
            ->with('success', 'Sales order confirmed.');
    }

    public function destroy(SalesOrder $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        $order->lines()->delete();
        $order->delete();

        return redirect()
            ->route('company.sales.orders.index')
            ->with('success', 'Sales order removed.');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function partnerOptions(): array
    {
        return Partner::query()
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
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
     * @return array<int, array{id: string, quote_number: string, partner_id: string}>
     */
    private function quoteOptions(Request $request): array
    {
        $user = $request->user();

        $query = SalesQuote::query()
            ->whereIn('status', [
                SalesQuote::STATUS_SENT,
                SalesQuote::STATUS_APPROVED,
                SalesQuote::STATUS_CONFIRMED,
            ])
            ->orderByDesc('created_at');

        if ($user) {
            $query = $user->applyDataScopeToQuery($query);
        }

        return $query
            ->limit(200)
            ->get(['id', 'quote_number', 'partner_id'])
            ->map(fn (SalesQuote $quote) => [
                'id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'partner_id' => $quote->partner_id,
            ])
            ->values()
            ->all();
    }
}


