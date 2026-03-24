<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Accounting\AccountingPaymentReverseRequest;
use App\Http\Requests\Accounting\AccountingPaymentStoreRequest;
use App\Http\Requests\Accounting\AccountingPaymentUpdateRequest;
use App\Models\User;
use App\Modules\Accounting\AccountingPaymentWorkflowService;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingPaymentsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountingPayment::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $invoiceId = trim((string) $request->input('invoice_id', ''));
        $method = trim((string) $request->input('method', ''));
        $externalReference = trim((string) $request->input('external_reference', ''));
        $bankReconciled = $this->booleanFilter($request, 'bank_reconciled');
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'updated_at', 'payment_date', 'payment_number', 'external_reference', 'status', 'amount', 'posted_at'],
            defaultSort: 'payment_date',
            defaultDirection: 'desc',
        );

        $payments = AccountingPayment::query()
            ->with([
                'invoice:id,invoice_number,status,partner_id',
                'invoice.partner:id,name',
            ])
            ->withCount('reconciliationEntries')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$search}%"))
                        ->orWhereHas('invoice.partner', fn ($partnerQuery) => $partnerQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($invoiceId !== '', fn ($query) => $query->where('invoice_id', $invoiceId))
            ->when($method !== '', fn ($query) => $query->where('method', $method))
            ->when($externalReference !== '', fn ($query) => $query->where('external_reference', $externalReference))
            ->when($bankReconciled !== null, function ($query) use ($bankReconciled) {
                if ($bankReconciled) {
                    $query->whereNotNull('bank_reconciled_at');
                } else {
                    $query->whereNull('bank_reconciled_at');
                }
            })
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $payments,
            data: collect($payments->items())
                ->map(fn (AccountingPayment $payment) => $this->mapPayment($payment, $user, false))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'invoice_id' => $invoiceId,
                'method' => $method,
                'external_reference' => $externalReference,
                'bank_reconciled' => $bankReconciled,
            ],
        );
    }

    public function store(
        AccountingPaymentStoreRequest $request,
        AccountingPaymentWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('create', AccountingPayment::class);

        $companyId = (string) $request->user()?->current_company_id;

        if ($companyId === '') {
            abort(403, 'Company context not available.');
        }

        $payment = $workflowService->createDraft(
            attributes: $request->validated(),
            companyId: $companyId,
            actorId: $request->user()?->id,
        );

        return $this->respond(
            $this->mapPayment(
                $payment->fresh($this->paymentRelationships())->loadCount('reconciliationEntries'),
                $request->user(),
            ),
            201,
        );
    }

    public function show(AccountingPayment $payment, Request $request): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->load($this->paymentRelationships())
            ->loadCount('reconciliationEntries');

        return $this->respond($this->mapPayment($payment, $request->user()));
    }

    public function update(
        AccountingPaymentUpdateRequest $request,
        AccountingPayment $payment,
        AccountingPaymentWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('update', $payment);

        $payment = $workflowService->updateDraft(
            payment: $payment,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return $this->respond(
            $this->mapPayment(
                $payment->fresh($this->paymentRelationships())->loadCount('reconciliationEntries'),
                $request->user(),
            ),
        );
    }

    public function post(
        Request $request,
        AccountingPayment $payment,
        AccountingPaymentWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('post', $payment);

        $payment = $workflowService->post($payment, $request->user()?->id);

        return $this->respond(
            $this->mapPayment(
                $payment->fresh($this->paymentRelationships())->loadCount('reconciliationEntries'),
                $request->user(),
            ),
        );
    }

    public function reconcile(
        Request $request,
        AccountingPayment $payment,
        AccountingPaymentWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('reconcile', $payment);

        $payment = $workflowService->reconcile($payment, $request->user()?->id);

        return $this->respond(
            $this->mapPayment(
                $payment->fresh($this->paymentRelationships())->loadCount('reconciliationEntries'),
                $request->user(),
            ),
        );
    }

    public function reverse(
        AccountingPaymentReverseRequest $request,
        AccountingPayment $payment,
        AccountingPaymentWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('reverse', $payment);

        $payment = $workflowService->reverse(
            payment: $payment,
            reason: (string) $request->validated('reason'),
            actorId: $request->user()?->id,
        );

        return $this->respond(
            $this->mapPayment(
                $payment->fresh($this->paymentRelationships())->loadCount('reconciliationEntries'),
                $request->user(),
            ),
        );
    }

    public function destroy(AccountingPayment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);

        $payment->delete();

        return $this->respondNoContent();
    }

    /**
     * @return array<int, string>
     */
    private function paymentRelationships(): array
    {
        return [
            'invoice:id,invoice_number,status,balance_due,partner_id',
            'invoice.partner:id,name',
            'reconciliationEntries:id,payment_id,entry_type,amount,reconciled_at',
        ];
    }

    private function mapPayment(
        AccountingPayment $payment,
        ?User $user = null,
        bool $includeRelations = true,
    ): array {
        $payload = [
            'id' => $payment->id,
            'external_reference' => $payment->external_reference,
            'invoice_id' => $payment->invoice_id,
            'invoice_number' => $payment->invoice?->invoice_number,
            'invoice_status' => $payment->invoice?->status,
            'invoice_balance_due' => $payment->invoice?->balance_due !== null
                ? (float) $payment->invoice->balance_due
                : null,
            'partner_name' => $payment->invoice?->partner?->name,
            'payment_number' => $payment->payment_number,
            'status' => $payment->status,
            'payment_date' => $payment->payment_date?->toDateString(),
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'posted_at' => $payment->posted_at?->toIso8601String(),
            'reconciled_at' => $payment->reconciled_at?->toIso8601String(),
            'bank_reconciled_at' => $payment->bank_reconciled_at?->toIso8601String(),
            'reversed_at' => $payment->reversed_at?->toIso8601String(),
            'reversal_reason' => $payment->reversal_reason,
            'reconciliations_count' => (int) ($payment->reconciliation_entries_count ?? $payment->reconciliationEntries()->count()),
            'updated_at' => $payment->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $payment) ?? false,
            'can_edit' => $user?->can('update', $payment) ?? false,
            'can_delete' => $user?->can('delete', $payment) ?? false,
            'can_post' => $user?->can('post', $payment) ?? false,
            'can_reconcile' => $user?->can('reconcile', $payment) ?? false,
            'can_reverse' => $user?->can('reverse', $payment) ?? false,
        ];

        if (! $includeRelations) {
            return $payload;
        }

        $payload['reconciliations'] = $payment->relationLoaded('reconciliationEntries')
            ? $payment->reconciliationEntries
                ->sortByDesc('reconciled_at')
                ->values()
                ->map(fn ($entry) => [
                    'id' => $entry->id,
                    'entry_type' => $entry->entry_type,
                    'amount' => (float) $entry->amount,
                    'reconciled_at' => $entry->reconciled_at?->toIso8601String(),
                ])
                ->all()
            : [];

        return $payload;
    }

    private function booleanFilter(Request $request, string $key): ?bool
    {
        if (! $request->query->has($key)) {
            return null;
        }

        return filter_var($request->query($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
