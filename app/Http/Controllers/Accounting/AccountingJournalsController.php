<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\AccountingSetupService;
use App\Modules\Accounting\Models\AccountingJournal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingJournalsController extends Controller
{
    public function index(Request $request, AccountingSetupService $setupService): Response
    {
        abort_unless($request->user()?->hasPermission('accounting.journals.view'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'journal_type' => ['nullable', 'string', 'in:sales,purchase,bank,general'],
        ]);

        $setupService->ensureCompanySetup(
            companyId: $company->id,
            currencyCode: $company->currency_code,
            actorId: $request->user()?->id,
        );

        $journals = AccountingJournal::query()
            ->with('defaultAccount:id,code,name')
            ->withCount('ledgerEntries')
            ->withSum('ledgerEntries as debit_total', 'debit')
            ->withSum('ledgerEntries as credit_total', 'credit')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($builder) use ($filters): void {
                    $term = '%'.trim((string) $filters['search']).'%';
                    $builder->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term);
                })
            )
            ->when(
                filled($filters['journal_type'] ?? null),
                fn ($query) => $query->where('journal_type', $filters['journal_type'])
            )
            ->orderBy('code')
            ->get();

        return Inertia::render('accounting/journals/index', [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'journal_type' => $filters['journal_type'] ?? '',
            ],
            'summary' => [
                'total_journals' => $journals->count(),
                'bank_journals' => $journals->where('journal_type', AccountingJournal::TYPE_BANK)->count(),
                'ledger_entries' => (int) $journals->sum('ledger_entries_count'),
            ],
            'journals' => $journals->map(fn (AccountingJournal $journal) => [
                'id' => $journal->id,
                'code' => $journal->code,
                'name' => $journal->name,
                'journal_type' => $journal->journal_type,
                'default_account' => $journal->defaultAccount
                    ? $journal->defaultAccount->code.' · '.$journal->defaultAccount->name
                    : null,
                'currency_code' => $journal->currency_code,
                'is_active' => (bool) $journal->is_active,
                'entry_count' => (int) ($journal->ledger_entries_count ?? 0),
                'debit_total' => round((float) ($journal->getAttribute('debit_total') ?? 0), 2),
                'credit_total' => round((float) ($journal->getAttribute('credit_total') ?? 0), 2),
            ])->values()->all(),
        ]);
    }
}
