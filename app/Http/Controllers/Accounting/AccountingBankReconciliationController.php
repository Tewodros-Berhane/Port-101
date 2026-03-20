<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountingBankReconciliationStoreRequest;
use App\Modules\Accounting\AccountingBankReconciliationService;
use App\Modules\Accounting\AccountingSetupService;
use App\Modules\Accounting\Models\AccountingBankReconciliationBatch;
use App\Modules\Accounting\Models\AccountingJournal;
use App\Modules\Accounting\Models\AccountingPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingBankReconciliationController extends Controller
{
    public function index(Request $request, AccountingSetupService $setupService): Response
    {
        $this->authorize('viewAny', AccountingBankReconciliationBatch::class);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $setupService->ensureCompanySetup(
            companyId: $company->id,
            currencyCode: $company->currency_code,
            actorId: $request->user()?->id,
        );

        $user = $request->user();

        $eligiblePayments = AccountingPayment::query()
            ->with(['invoice:id,invoice_number,partner_id', 'invoice.partner:id,name'])
            ->whereIn('status', [
                AccountingPayment::STATUS_POSTED,
                AccountingPayment::STATUS_RECONCILED,
            ])
            ->whereNull('bank_reconciled_at')
            ->latest('payment_date')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder))
            ->get()
            ->map(fn (AccountingPayment $payment) => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'status' => $payment->status,
                'invoice_number' => $payment->invoice?->invoice_number,
                'partner_name' => $payment->invoice?->partner?->name,
                'payment_date' => $payment->payment_date?->toDateString(),
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
            ])
            ->values()
            ->all();

        $recentBatches = AccountingBankReconciliationBatch::query()
            ->with(['journal:id,code,name', 'items:id,batch_id,amount'])
            ->latest('statement_date')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder))
            ->limit(10)
            ->get()
            ->map(function (AccountingBankReconciliationBatch $batch) {
                $totalAmount = round((float) $batch->items->sum('amount'), 2);

                return [
                    'id' => $batch->id,
                    'statement_reference' => $batch->statement_reference,
                    'statement_date' => $batch->statement_date?->toDateString(),
                    'journal_name' => $batch->journal?->name,
                    'journal_code' => $batch->journal?->code,
                    'item_count' => $batch->items->count(),
                    'total_amount' => $totalAmount,
                    'reconciled_at' => $batch->reconciled_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $bankJournals = AccountingJournal::query()
            ->where('journal_type', AccountingJournal::TYPE_BANK)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (AccountingJournal $journal) => [
                'id' => $journal->id,
                'code' => $journal->code,
                'name' => $journal->name,
            ])
            ->values()
            ->all();

        return Inertia::render('accounting/bank-reconciliation/index', [
            'filters' => [
                'journal_id' => $bankJournals[0]['id'] ?? '',
                'statement_reference' => '',
                'statement_date' => now()->toDateString(),
                'notes' => '',
            ],
            'bankJournals' => $bankJournals,
            'eligiblePayments' => $eligiblePayments,
            'recentBatches' => $recentBatches,
        ]);
    }

    public function store(
        AccountingBankReconciliationStoreRequest $request,
        AccountingBankReconciliationService $service,
    ): RedirectResponse {
        $this->authorize('create', AccountingBankReconciliationBatch::class);

        $companyId = (string) $request->user()?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $service->createBatch(
            attributes: $request->validated(),
            companyId: $companyId,
            actor: $request->user(),
        );

        return redirect()
            ->route('company.accounting.bank-reconciliation.index')
            ->with('success', 'Bank reconciliation batch created.');
    }
}
