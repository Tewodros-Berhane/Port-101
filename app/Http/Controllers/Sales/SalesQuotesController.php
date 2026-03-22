<?php

namespace App\Http\Controllers\Sales;

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesQuoteRejectRequest;
use App\Http\Requests\Sales\SalesQuoteStoreRequest;
use App\Http\Requests\Sales\SalesQuoteUpdateRequest;
use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Sales\SalesQuoteWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesQuotesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalesQuote::class);

        $user = $request->user();

        $quotesQuery = SalesQuote::query()
            ->with(['partner:id,name', 'lead:id,title'])
            ->orderByDesc('created_at')
            ->when($user, fn ($query) => $user->applyDataScopeToQuery($query));

        $quotes = $quotesQuery->paginate(20)->withQueryString();

        return Inertia::render('sales/quotes/index', [
            'quotes' => $quotes->through(function (SalesQuote $quote) {
                return [
                    'id' => $quote->id,
                    'quote_number' => $quote->quote_number,
                    'status' => $quote->status,
                    'partner_name' => $quote->partner?->name,
                    'lead_title' => $quote->lead?->title,
                    'quote_date' => $quote->quote_date?->toDateString(),
                    'valid_until' => $quote->valid_until?->toDateString(),
                    'grand_total' => (float) $quote->grand_total,
                    'requires_approval' => (bool) $quote->requires_approval,
                    'approved_at' => $quote->approved_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', SalesQuote::class);

        $leadId = $request->string('lead')->toString();

        return Inertia::render('sales/quotes/create', [
            'quote' => [
                'lead_id' => $leadId ?: null,
                'quote_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
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
            'leads' => $this->leadOptions($request),
        ]);
    }

    public function store(
        SalesQuoteStoreRequest $request,
        SalesQuoteWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('create', SalesQuote::class);

        $quote = $workflowService->create($request->validated(), $request->user());

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote created.');
    }

    public function edit(SalesQuote $quote): Response
    {
        $this->authorize('update', $quote);

        $quote->load(['lines', 'order']);

        return Inertia::render('sales/quotes/edit', [
            'quote' => [
                'id' => $quote->id,
                'lead_id' => $quote->lead_id,
                'partner_id' => $quote->partner_id,
                'quote_number' => $quote->quote_number,
                'status' => $quote->status,
                'quote_date' => $quote->quote_date?->toDateString(),
                'valid_until' => $quote->valid_until?->toDateString(),
                'subtotal' => (float) $quote->subtotal,
                'discount_total' => (float) $quote->discount_total,
                'tax_total' => (float) $quote->tax_total,
                'grand_total' => (float) $quote->grand_total,
                'requires_approval' => (bool) $quote->requires_approval,
                'approved_at' => $quote->approved_at?->toIso8601String(),
                'order_id' => $quote->order?->id,
                'lines' => $quote->lines->map(fn ($line) => [
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
            'leads' => $this->leadOptions(request()),
        ]);
    }

    public function update(
        SalesQuoteUpdateRequest $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('update', $quote);

        $workflowService->update($quote, $request->validated(), $request->user());

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote updated.');
    }

    public function send(
        Request $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('update', $quote);

        try {
            $workflowService->send($quote, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', $this->workflowErrorMessage($exception));
        }

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote marked as sent.');
    }

    public function approve(
        Request $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('approve', $quote);

        try {
            $workflowService->approve($quote, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', $this->workflowErrorMessage($exception));
        }

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote approved.');
    }

    public function reject(
        SalesQuoteRejectRequest $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('approve', $quote);

        try {
            $workflowService->reject(
                $quote,
                $request->user(),
                $request->validated('reason')
            );
        } catch (ValidationException $exception) {
            return back()->with('error', $this->workflowErrorMessage($exception));
        }

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote rejected.');
    }

    public function confirm(
        Request $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('confirm', $quote);

        try {
            $order = $workflowService->confirm($quote, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', $this->workflowErrorMessage($exception));
        }

        return redirect()
            ->route('company.sales.orders.edit', $order)
            ->with('success', 'Quote confirmed and sales order created.');
    }

    public function destroy(
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): RedirectResponse {
        $this->authorize('delete', $quote);

        $workflowService->delete($quote);

        return redirect()
            ->route('company.sales.quotes.index')
            ->with('success', 'Quote removed.');
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
     * @return array<int, array{id: string, title: string}>
     */
    private function leadOptions(Request $request): array
    {
        $user = $request->user();

        $query = SalesLead::query()->orderByDesc('created_at');

        if ($user) {
            $query = $user->applyDataScopeToQuery($query);
        }

        return $query
            ->limit(200)
            ->get(['id', 'title'])
            ->map(fn (SalesLead $lead) => [
                'id' => $lead->id,
                'title' => $lead->title,
            ])
            ->values()
            ->all();
    }

    private function workflowErrorMessage(ValidationException $exception): string
    {
        return (string) (collect($exception->errors())->flatten()->first() ?? $exception->getMessage());
    }
}
