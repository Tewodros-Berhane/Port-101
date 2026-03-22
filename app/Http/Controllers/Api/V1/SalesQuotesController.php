<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Sales\SalesQuoteRejectRequest;
use App\Http\Requests\Sales\SalesQuoteStoreRequest;
use App\Http\Requests\Sales\SalesQuoteUpdateRequest;
use App\Models\User;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Sales\SalesQuoteWorkflowService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesQuotesController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesQuote::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $requiresApproval = $this->booleanFilter($request, 'requires_approval');
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'quote_number', 'status', 'quote_date', 'valid_until', 'grand_total', 'updated_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $quotes = SalesQuote::query()
            ->with(['partner:id,name', 'lead:id,title', 'order:id,quote_id,order_number', 'approvedBy:id,name'])
            ->withCount('lines')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('quote_number', 'like', "%{$search}%")
                        ->orWhereHas('partner', fn ($partnerQuery) => $partnerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('lead', fn ($leadQuery) => $leadQuery->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($requiresApproval !== null, fn ($query) => $query->where('requires_approval', $requiresApproval))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $quotes,
            data: collect($quotes->items())
                ->map(fn (SalesQuote $quote) => $this->mapQuote($quote, $user, false))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'requires_approval' => $requiresApproval,
            ],
        );
    }

    public function store(
        SalesQuoteStoreRequest $request,
        SalesQuoteWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('create', SalesQuote::class);

        $quote = $workflowService->create($request->validated(), $request->user());

        return $this->respond($this->mapQuote($quote, $request->user()), 201);
    }

    public function show(SalesQuote $quote, Request $request): JsonResponse
    {
        $this->authorize('view', $quote);

        $quote->load([
            'partner:id,name',
            'lead:id,title',
            'order:id,quote_id,order_number',
            'approvedBy:id,name',
            'lines.product:id,name,sku',
        ])->loadCount('lines');

        return $this->respond($this->mapQuote($quote, $request->user()));
    }

    public function update(
        SalesQuoteUpdateRequest $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('update', $quote);

        $quote = $workflowService->update($quote, $request->validated(), $request->user());

        return $this->respond($this->mapQuote($quote, $request->user()));
    }

    public function send(
        Request $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('update', $quote);

        $quote = $workflowService->send($quote, $request->user());

        return $this->respond($this->mapQuote($quote, $request->user()));
    }

    public function approve(
        Request $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('approve', $quote);

        $quote = $workflowService->approve($quote, $request->user());

        return $this->respond($this->mapQuote($quote, $request->user()));
    }

    public function reject(
        SalesQuoteRejectRequest $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('approve', $quote);

        $quote = $workflowService->reject($quote, $request->user(), $request->validated('reason'));

        return $this->respond($this->mapQuote($quote, $request->user()));
    }

    public function confirm(
        Request $request,
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('confirm', $quote);

        $order = $workflowService->confirm($quote, $request->user());

        return $this->respond([
            'quote' => $this->mapQuote(
                $quote->fresh([
                    'partner:id,name',
                    'lead:id,title',
                    'order:id,quote_id,order_number',
                    'approvedBy:id,name',
                    'lines.product:id,name,sku',
                ])->loadCount('lines'),
                $request->user(),
            ),
            'order' => $this->mapOrderSummary($order, $request->user()),
        ]);
    }

    public function destroy(
        SalesQuote $quote,
        SalesQuoteWorkflowService $workflowService
    ): JsonResponse {
        $this->authorize('delete', $quote);

        $workflowService->delete($quote);

        return $this->respondNoContent();
    }

    private function mapQuote(SalesQuote $quote, ?User $user = null, bool $includeLines = true): array
    {
        $payload = [
            'id' => $quote->id,
            'lead_id' => $quote->lead_id,
            'lead_title' => $quote->lead?->title,
            'partner_id' => $quote->partner_id,
            'partner_name' => $quote->partner?->name,
            'quote_number' => $quote->quote_number,
            'status' => $quote->status,
            'quote_date' => $quote->quote_date?->toDateString(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'subtotal' => (float) $quote->subtotal,
            'discount_total' => (float) $quote->discount_total,
            'tax_total' => (float) $quote->tax_total,
            'grand_total' => (float) $quote->grand_total,
            'requires_approval' => (bool) $quote->requires_approval,
            'approved_by' => $quote->approved_by,
            'approved_by_name' => $quote->approvedBy?->name,
            'approved_at' => $quote->approved_at?->toIso8601String(),
            'rejection_reason' => $quote->rejection_reason,
            'order_id' => $quote->order?->id,
            'order_number' => $quote->order?->order_number,
            'lines_count' => (int) ($quote->lines_count ?? $quote->lines()->count()),
            'updated_at' => $quote->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $quote) ?? false,
            'can_edit' => $user?->can('update', $quote) ?? false,
            'can_delete' => $user?->can('delete', $quote) ?? false,
            'can_approve' => $user?->can('approve', $quote) ?? false,
            'can_confirm' => $user?->can('confirm', $quote) ?? false,
            'can_send' => ($user?->can('update', $quote) ?? false)
                && in_array($quote->status, [SalesQuote::STATUS_DRAFT, SalesQuote::STATUS_REJECTED], true),
        ];

        if (! $includeLines) {
            return $payload;
        }

        $payload['lines'] = $quote->relationLoaded('lines')
            ? $quote->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product?->name,
                'product_sku' => $line->product?->sku,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount_percent' => (float) $line->discount_percent,
                'tax_rate' => (float) $line->tax_rate,
                'line_subtotal' => (float) $line->line_subtotal,
                'line_total' => (float) $line->line_total,
            ])->values()->all()
            : [];

        return $payload;
    }

    private function mapOrderSummary(SalesOrder $order, ?User $user = null): array
    {
        return [
            'id' => $order->id,
            'quote_id' => $order->quote_id,
            'quote_number' => $order->quote?->quote_number,
            'partner_id' => $order->partner_id,
            'partner_name' => $order->partner?->name,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'order_date' => $order->order_date?->toDateString(),
            'grand_total' => (float) $order->grand_total,
            'requires_approval' => (bool) $order->requires_approval,
            'approved_at' => $order->approved_at?->toIso8601String(),
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'can_view' => $user?->can('view', $order) ?? false,
            'can_edit' => $user?->can('update', $order) ?? false,
            'can_approve' => $user?->can('approve', $order) ?? false,
            'can_confirm' => $user?->can('confirm', $order) ?? false,
        ];
    }

    private function booleanFilter(Request $request, string $key): ?bool
    {
        if (! $request->query->has($key)) {
            return null;
        }

        return filter_var($request->query($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
