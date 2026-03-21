<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AccountingBankReconciliationStoreRequest;
use App\Http\Requests\Accounting\AccountingBankReconciliationUnreconcileRequest;
use App\Http\Requests\Accounting\AccountingBankStatementImportRequest;
use App\Modules\Accounting\AccountingBankReconciliationService;
use App\Modules\Accounting\AccountingBankStatementImportService;
use App\Modules\Accounting\AccountingSetupService;
use App\Modules\Accounting\Models\AccountingBankReconciliationBatch;
use App\Modules\Accounting\Models\AccountingBankStatementImport;
use App\Modules\Accounting\Models\AccountingBankStatementImportLine;
use App\Modules\Accounting\Models\AccountingJournal;
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

        $filters = $request->validate([
            'import_id' => ['nullable', 'uuid'],
        ]);

        $recentBatches = AccountingBankReconciliationBatch::query()
            ->with([
                'journal:id,code,name',
                'items:id,batch_id,amount',
                'reconciledBy:id,name',
                'unreconciledBy:id,name',
            ])
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
                    'reconciled_by' => $batch->reconciledBy?->name,
                    'unreconciled_at' => $batch->unreconciled_at?->toIso8601String(),
                    'unreconciled_by' => $batch->unreconciledBy?->name,
                    'unreconcile_reason' => $batch->unreconcile_reason,
                    'can_unreconcile' => $user?->can('unreconcile', $batch) ?? false,
                ];
            })
            ->values()
            ->all();

        $recentImports = AccountingBankStatementImport::query()
            ->with(['journal:id,code,name', 'lines:id,bank_statement_import_id,match_status', 'reconciledBatch:id,statement_reference'])
            ->latest('statement_date')
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder))
            ->limit(10)
            ->get()
            ->map(function (AccountingBankStatementImport $statementImport) {
                $matchedCount = $statementImport->lines
                    ->where('match_status', AccountingBankStatementImportLine::MATCH_STATUS_MATCHED)
                    ->count();
                $unmatchedCount = $statementImport->lines
                    ->where('match_status', AccountingBankStatementImportLine::MATCH_STATUS_UNMATCHED)
                    ->count();
                $duplicateCount = $statementImport->lines
                    ->where('match_status', AccountingBankStatementImportLine::MATCH_STATUS_DUPLICATE)
                    ->count();

                return [
                    'id' => $statementImport->id,
                    'statement_reference' => $statementImport->statement_reference,
                    'statement_date' => $statementImport->statement_date?->toDateString(),
                    'journal_name' => $statementImport->journal?->name,
                    'journal_code' => $statementImport->journal?->code,
                    'source_file_name' => $statementImport->source_file_name,
                    'matched_count' => $matchedCount,
                    'unmatched_count' => $unmatchedCount,
                    'duplicate_count' => $duplicateCount,
                    'reconciled_batch_id' => $statementImport->reconciled_batch_id,
                    'reconciled_batch_reference' => $statementImport->reconciledBatch?->statement_reference,
                ];
            })
            ->values()
            ->all();

        $activeImport = null;

        if (! empty($filters['import_id'])) {
            $statementImport = AccountingBankStatementImport::query()
                ->with([
                    'journal:id,code,name',
                    'lines.payment.invoice.partner',
                    'reconciledBatch:id,statement_reference',
                ])
                ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder))
                ->findOrFail($filters['import_id']);

            $activeImport = [
                'id' => $statementImport->id,
                'statement_reference' => $statementImport->statement_reference,
                'statement_date' => $statementImport->statement_date?->toDateString(),
                'journal_name' => $statementImport->journal?->name,
                'journal_code' => $statementImport->journal?->code,
                'source_file_name' => $statementImport->source_file_name,
                'notes' => $statementImport->notes,
                'reconciled_batch_id' => $statementImport->reconciled_batch_id,
                'reconciled_batch_reference' => $statementImport->reconciledBatch?->statement_reference,
                'metrics' => [
                    'matched' => $statementImport->lines
                        ->where('match_status', AccountingBankStatementImportLine::MATCH_STATUS_MATCHED)
                        ->count(),
                    'unmatched' => $statementImport->lines
                        ->where('match_status', AccountingBankStatementImportLine::MATCH_STATUS_UNMATCHED)
                        ->count(),
                    'duplicate' => $statementImport->lines
                        ->where('match_status', AccountingBankStatementImportLine::MATCH_STATUS_DUPLICATE)
                        ->count(),
                ],
                'lines' => $statementImport->lines
                    ->map(fn (AccountingBankStatementImportLine $line) => [
                        'id' => $line->id,
                        'line_number' => $line->line_number,
                        'transaction_date' => $line->transaction_date?->toDateString(),
                        'reference' => $line->reference,
                        'description' => $line->description,
                        'amount' => (float) $line->amount,
                        'match_status' => $line->match_status,
                        'payment_id' => $line->payment_id,
                        'payment_number' => $line->payment?->payment_number,
                        'invoice_number' => $line->payment?->invoice?->invoice_number,
                        'partner_name' => $line->payment?->invoice?->partner?->name,
                    ])
                    ->values()
                    ->all(),
            ];
        }

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
            'importForm' => [
                'journal_id' => $bankJournals[0]['id'] ?? '',
                'statement_reference' => '',
                'statement_date' => now()->toDateString(),
                'notes' => '',
            ],
            'bankJournals' => $bankJournals,
            'activeImport' => $activeImport,
            'recentImports' => $recentImports,
            'recentBatches' => $recentBatches,
        ]);
    }

    public function import(
        AccountingBankStatementImportRequest $request,
        AccountingBankStatementImportService $importService,
    ): RedirectResponse {
        $this->authorize('create', AccountingBankReconciliationBatch::class);

        $companyId = (string) $request->user()?->current_company_id;

        if (! $companyId) {
            abort(403, 'Company context not available.');
        }

        $statementImport = $importService->import(
            file: $request->file('file'),
            attributes: $request->validated(),
            companyId: $companyId,
            actor: $request->user(),
        );

        return redirect()
            ->route('company.accounting.bank-reconciliation.index', [
                'import_id' => $statementImport->id,
            ])
            ->with('success', 'Bank statement imported. Review the matched lines before reconciliation.');
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

        $data = $request->validated();

        if (! empty($data['bank_statement_import_id'])) {
            $statementImport = AccountingBankStatementImport::query()
                ->when($request->user(), fn ($builder) => $request->user()?->applyDataScopeToQuery($builder))
                ->where('company_id', $companyId)
                ->findOrFail((string) $data['bank_statement_import_id']);

            $service->createBatchFromImport(
                statementImport: $statementImport,
                lineIds: array_map('strval', $data['line_ids'] ?? []),
                actor: $request->user(),
            );
        } else {
            $service->createBatch(
                attributes: $data,
                companyId: $companyId,
                actor: $request->user(),
            );
        }

        return redirect()
            ->route('company.accounting.bank-reconciliation.index')
            ->with('success', 'Bank reconciliation batch created.');
    }

    public function unreconcile(
        AccountingBankReconciliationUnreconcileRequest $request,
        AccountingBankReconciliationBatch $batch,
        AccountingBankReconciliationService $service,
    ): RedirectResponse {
        $this->authorize('unreconcile', $batch);

        $service->unreconcileBatch(
            batch: $batch,
            reason: (string) $request->validated('reason'),
            actor: $request->user(),
        );

        return redirect()
            ->route('company.accounting.bank-reconciliation.index')
            ->with('success', 'Bank reconciliation batch unreconciled.');
    }
}
