<?php

namespace App\Http\Controllers\Sales;

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesOrderStoreRequest;
use App\Http\Requests\Sales\SalesOrderUpdateRequest;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Sales\SalesOrderWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        SalesOrderWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('create', SalesOrder::class);

        $order = $workflowService->create($request->validated(), $request->user());

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
        SalesOrderWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('update', $order);

        $workflowService->update($order, $request->validated(), $request->user());

        return redirect()
            ->route('company.sales.orders.edit', $order)
            ->with('success', 'Sales order updated.');
    }

    public function approve(
        Request $request,
        SalesOrder $order,
        SalesOrderWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('approve', $order);

        try {
            $workflowService->approve($order, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', $this->workflowErrorMessage($exception));
        }

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

        try {
            $workflowService->confirm($order, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', $this->workflowErrorMessage($exception));
        }

        return redirect()
            ->route('company.sales.orders.edit', $order)
            ->with('success', 'Sales order confirmed.');
    }

    public function destroy(
        SalesOrder $order,
        SalesOrderWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('delete', $order);

        $workflowService->delete($order);

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

    private function workflowErrorMessage(ValidationException $exception): string
    {
        return (string) (collect($exception->errors())->flatten()->first() ?? $exception->getMessage());
    }
}
