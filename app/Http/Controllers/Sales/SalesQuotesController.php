<?php

namespace App\Http\Controllers\Sales;

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\Sales\SalesApprovalPolicyService;
use App\Core\Sales\SalesDocumentTotalsService;
use App\Core\Sales\SalesNumberingService;
use App\Core\Sales\SalesQuoteConversionService;
use App\Core\Sales\Models\SalesLead;
use App\Core\Sales\Models\SalesQuote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesQuoteStoreRequest;
use App\Http\Requests\Sales\SalesQuoteUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        SalesDocumentTotalsService $totalsService,
        SalesApprovalPolicyService $approvalPolicyService,
        SalesNumberingService $numberingService
    ): RedirectResponse {
        $this->authorize('create', SalesQuote::class);

        $user = $request->user();
        $companyId = $user?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $validated = $request->validated();
        $calculated = $totalsService->calculate($validated['lines']);
        $totals = $calculated['totals'];

        $quote = DB::transaction(function () use (
            $validated,
            $calculated,
            $totals,
            $approvalPolicyService,
            $numberingService,
            $companyId,
            $user
        ) {
            $quote = SalesQuote::create([
                'company_id' => $companyId,
                'lead_id' => $validated['lead_id'] ?? null,
                'partner_id' => $validated['partner_id'],
                'quote_number' => $numberingService->nextQuoteNumber($companyId, $user?->id),
                'status' => SalesQuote::STATUS_DRAFT,
                'quote_date' => $validated['quote_date'],
                'valid_until' => $validated['valid_until'] ?? null,
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

            $quote->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $user?->id,
                        'updated_by' => $user?->id,
                    ])
                    ->all()
            );

            if ($quote->lead_id) {
                SalesLead::query()
                    ->where('id', $quote->lead_id)
                    ->update([
                        'stage' => 'quoted',
                        'updated_by' => $user?->id,
                    ]);
            }

            return $quote;
        });

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
        SalesDocumentTotalsService $totalsService,
        SalesApprovalPolicyService $approvalPolicyService
    ): RedirectResponse {
        $this->authorize('update', $quote);

        $validated = $request->validated();
        $user = $request->user();
        $companyId = (string) $quote->company_id;

        $calculated = $totalsService->calculate($validated['lines']);
        $totals = $calculated['totals'];

        DB::transaction(function () use (
            $quote,
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

            $resetApproval = $quote->status === SalesQuote::STATUS_APPROVED;

            $quote->update([
                'lead_id' => $validated['lead_id'] ?? null,
                'partner_id' => $validated['partner_id'],
                'quote_date' => $validated['quote_date'],
                'valid_until' => $validated['valid_until'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'requires_approval' => $requiresApproval,
                'approved_by' => $resetApproval ? null : $quote->approved_by,
                'approved_at' => $resetApproval ? null : $quote->approved_at,
                'status' => $resetApproval
                    ? SalesQuote::STATUS_DRAFT
                    : ($quote->status === SalesQuote::STATUS_REJECTED
                        ? SalesQuote::STATUS_DRAFT
                        : $quote->status),
                'updated_by' => $user?->id,
            ]);

            $quote->lines()->delete();
            $quote->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'company_id' => $companyId,
                        'created_by' => $user?->id,
                        'updated_by' => $user?->id,
                    ])
                    ->all()
            );

            if ($quote->lead_id) {
                SalesLead::query()
                    ->where('id', $quote->lead_id)
                    ->update([
                        'stage' => 'quoted',
                        'updated_by' => $user?->id,
                    ]);
            }
        });

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote updated.');
    }

    public function send(Request $request, SalesQuote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        if (! in_array($quote->status, [SalesQuote::STATUS_DRAFT, SalesQuote::STATUS_REJECTED], true)) {
            return back()->with('error', 'Only draft or rejected quotes can be sent.');
        }

        $quote->update([
            'status' => SalesQuote::STATUS_SENT,
            'rejection_reason' => null,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote marked as sent.');
    }

    public function approve(Request $request, SalesQuote $quote): RedirectResponse
    {
        $this->authorize('approve', $quote);

        if ($quote->status === SalesQuote::STATUS_CONFIRMED) {
            return back()->with('error', 'Confirmed quotes cannot be approved again.');
        }

        $quote->update([
            'status' => SalesQuote::STATUS_APPROVED,
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'rejection_reason' => null,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote approved.');
    }

    public function reject(Request $request, SalesQuote $quote): RedirectResponse
    {
        $this->authorize('approve', $quote);

        if ($quote->status === SalesQuote::STATUS_CONFIRMED) {
            return back()->with('error', 'Confirmed quotes cannot be rejected.');
        }

        $reason = $request->string('reason')->toString();

        $quote->update([
            'status' => SalesQuote::STATUS_REJECTED,
            'rejection_reason' => $reason ?: null,
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.sales.quotes.edit', $quote)
            ->with('success', 'Quote rejected.');
    }

    public function confirm(
        Request $request,
        SalesQuote $quote,
        SalesQuoteConversionService $conversionService
    ): RedirectResponse {
        $this->authorize('confirm', $quote);

        if ($quote->requires_approval && $quote->status !== SalesQuote::STATUS_APPROVED) {
            return back()->with('error', 'This quote requires manager approval before confirmation.');
        }

        $quote->loadMissing(['lines', 'order']);

        $order = $conversionService->createOrderFromQuote($quote, $request->user());

        $quote->update([
            'status' => SalesQuote::STATUS_CONFIRMED,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.sales.orders.edit', $order)
            ->with('success', 'Quote confirmed and sales order created.');
    }

    public function destroy(SalesQuote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        $quote->lines()->delete();
        $quote->delete();

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
}
