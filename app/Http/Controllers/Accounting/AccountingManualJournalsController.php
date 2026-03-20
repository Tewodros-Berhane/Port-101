<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountingManualJournalReverseRequest;
use App\Http\Requests\Accounting\AccountingManualJournalStoreRequest;
use App\Http\Requests\Accounting\AccountingManualJournalUpdateRequest;
use App\Modules\Accounting\AccountingManualJournalWorkflowService;
use App\Modules\Accounting\AccountingSetupService;
use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingJournal;
use App\Modules\Accounting\Models\AccountingManualJournal;
use App\Modules\Accounting\Models\AccountingManualJournalLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingManualJournalsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AccountingManualJournal::class);

        $user = $request->user();

        $journals = AccountingManualJournal::query()
            ->with([
                'journal:id,code,name',
                'lines:id,manual_journal_id,debit,credit',
            ])
            ->latest('entry_date')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder))
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('accounting/manual-journals/index', [
            'journals' => $journals->through(function (AccountingManualJournal $manualJournal) {
                $totalDebit = round((float) $manualJournal->lines->sum('debit'), 2);
                $totalCredit = round((float) $manualJournal->lines->sum('credit'), 2);

                return [
                    'id' => $manualJournal->id,
                    'entry_number' => $manualJournal->entry_number,
                    'status' => $manualJournal->status,
                    'entry_date' => $manualJournal->entry_date?->toDateString(),
                    'reference' => $manualJournal->reference,
                    'description' => $manualJournal->description,
                    'journal_name' => $manualJournal->journal?->name,
                    'journal_code' => $manualJournal->journal?->code,
                    'line_count' => $manualJournal->lines->count(),
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                ];
            }),
        ]);
    }

    public function create(Request $request, AccountingSetupService $setupService): Response
    {
        $this->authorize('create', AccountingManualJournal::class);

        $this->ensureAccountingSetup($request, $setupService);

        $journals = $this->journalOptions();

        return Inertia::render('accounting/manual-journals/create', [
            'manualJournal' => [
                'journal_id' => $journals[0]['id'] ?? '',
                'entry_date' => now()->toDateString(),
                'reference' => '',
                'description' => '',
                'lines' => [
                    [
                        'account_id' => '',
                        'description' => '',
                        'debit' => 0,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => '',
                        'description' => '',
                        'debit' => 0,
                        'credit' => 0,
                    ],
                ],
            ],
            'journals' => $journals,
            'accounts' => $this->accountOptions(),
        ]);
    }

    public function store(
        AccountingManualJournalStoreRequest $request,
        AccountingManualJournalWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('create', AccountingManualJournal::class);

        $companyId = (string) $request->user()?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $manualJournal = $workflowService->createDraft(
            attributes: $request->validated(),
            companyId: $companyId,
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.accounting.manual-journals.edit', $manualJournal)
            ->with('success', 'Manual journal created.');
    }

    public function edit(
        Request $request,
        AccountingManualJournal $manualJournal,
        AccountingSetupService $setupService,
    ): Response {
        $this->authorize('view', $manualJournal);

        $this->ensureAccountingSetup($request, $setupService);

        $manualJournal->load([
            'journal:id,code,name',
            'lines.account:id,code,name',
        ]);

        return Inertia::render('accounting/manual-journals/edit', [
            'manualJournal' => [
                'id' => $manualJournal->id,
                'entry_number' => $manualJournal->entry_number,
                'journal_id' => $manualJournal->journal_id,
                'journal_name' => $manualJournal->journal?->name,
                'status' => $manualJournal->status,
                'entry_date' => $manualJournal->entry_date?->toDateString(),
                'reference' => $manualJournal->reference,
                'description' => $manualJournal->description,
                'posted_at' => $manualJournal->posted_at?->toIso8601String(),
                'reversed_at' => $manualJournal->reversed_at?->toIso8601String(),
                'reversal_reason' => $manualJournal->reversal_reason,
                'lines' => $manualJournal->lines
                    ->map(fn (AccountingManualJournalLine $line) => [
                        'id' => $line->id,
                        'account_id' => $line->account_id,
                        'account_code' => $line->account?->code,
                        'account_name' => $line->account?->name,
                        'description' => $line->description,
                        'debit' => (float) $line->debit,
                        'credit' => (float) $line->credit,
                    ])
                    ->values()
                    ->all(),
            ],
            'journals' => $this->journalOptions(),
            'accounts' => $this->accountOptions(),
        ]);
    }

    public function update(
        AccountingManualJournalUpdateRequest $request,
        AccountingManualJournal $manualJournal,
        AccountingManualJournalWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('update', $manualJournal);

        $workflowService->updateDraft(
            manualJournal: $manualJournal,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.accounting.manual-journals.edit', $manualJournal)
            ->with('success', 'Manual journal updated.');
    }

    public function post(
        Request $request,
        AccountingManualJournal $manualJournal,
        AccountingManualJournalWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('post', $manualJournal);

        $workflowService->post($manualJournal, $request->user()?->id);

        return redirect()
            ->route('company.accounting.manual-journals.edit', $manualJournal)
            ->with('success', 'Manual journal posted.');
    }

    public function reverse(
        AccountingManualJournalReverseRequest $request,
        AccountingManualJournal $manualJournal,
        AccountingManualJournalWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('reverse', $manualJournal);

        $workflowService->reverse(
            manualJournal: $manualJournal,
            reason: (string) $request->validated('reason'),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.accounting.manual-journals.edit', $manualJournal)
            ->with('success', 'Manual journal reversed.');
    }

    public function destroy(AccountingManualJournal $manualJournal): RedirectResponse
    {
        $this->authorize('delete', $manualJournal);

        $manualJournal->lines()->delete();
        $manualJournal->delete();

        return redirect()
            ->route('company.accounting.manual-journals.index')
            ->with('success', 'Manual journal deleted.');
    }

    /**
     * @return array<int, array{id: string, code: string, name: string}>
     */
    private function journalOptions(): array
    {
        return AccountingJournal::query()
            ->where('journal_type', AccountingJournal::TYPE_GENERAL)
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
    }

    /**
     * @return array<int, array{id: string, code: string, name: string, account_type: string}>
     */
    private function accountOptions(): array
    {
        return AccountingAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type'])
            ->map(fn (AccountingAccount $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
            ])
            ->values()
            ->all();
    }

    private function ensureAccountingSetup(Request $request, AccountingSetupService $setupService): void
    {
        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $setupService->ensureCompanySetup(
            companyId: $company->id,
            currencyCode: $company->currency_code,
            actorId: $request->user()?->id,
        );
    }
}
