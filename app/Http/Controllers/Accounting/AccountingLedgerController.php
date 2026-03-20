<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\AccountingSetupService;
use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingJournal;
use App\Modules\Accounting\Models\AccountingLedgerEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingLedgerController extends Controller
{
    public function index(Request $request, AccountingSetupService $setupService): Response
    {
        abort_unless($request->user()?->hasPermission('accounting.ledger.view'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $filters = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'journal_id' => ['nullable', 'uuid'],
            'account_id' => ['nullable', 'uuid'],
            'source_action' => ['nullable', 'string', 'in:invoice_post,invoice_cancel,payment_post,payment_reverse'],
        ]);

        $setupService->ensureCompanySetup(
            companyId: $company->id,
            currencyCode: $company->currency_code,
            actorId: $request->user()?->id,
        );

        $query = AccountingLedgerEntry::query()
            ->with([
                'journal:id,code,name,journal_type',
                'account:id,code,name,normal_balance',
            ])
            ->when(
                filled($filters['start_date'] ?? null),
                fn ($builder) => $builder->whereDate('transaction_date', '>=', $filters['start_date'])
            )
            ->when(
                filled($filters['end_date'] ?? null),
                fn ($builder) => $builder->whereDate('transaction_date', '<=', $filters['end_date'])
            )
            ->when(
                filled($filters['journal_id'] ?? null),
                fn ($builder) => $builder->where('journal_id', $filters['journal_id'])
            )
            ->when(
                filled($filters['account_id'] ?? null),
                fn ($builder) => $builder->where('account_id', $filters['account_id'])
            )
            ->when(
                filled($filters['source_action'] ?? null),
                fn ($builder) => $builder->where('source_action', $filters['source_action'])
            );

        $entries = (clone $query)
            ->latest('transaction_date')
            ->latest('created_at')
            ->paginate(40)
            ->withQueryString();

        $summaryQuery = clone $query;

        return Inertia::render('accounting/ledger/index', [
            'filters' => [
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
                'journal_id' => $filters['journal_id'] ?? '',
                'account_id' => $filters['account_id'] ?? '',
                'source_action' => $filters['source_action'] ?? '',
            ],
            'summary' => [
                'entry_count' => (clone $summaryQuery)->count(),
                'group_count' => (clone $summaryQuery)->distinct('entry_group_uuid')->count('entry_group_uuid'),
                'total_debit' => round((float) (clone $summaryQuery)->sum('debit'), 2),
                'total_credit' => round((float) (clone $summaryQuery)->sum('credit'), 2),
            ],
            'journals' => AccountingJournal::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (AccountingJournal $journal) => [
                    'id' => $journal->id,
                    'label' => $journal->code.' · '.$journal->name,
                ])
                ->values()
                ->all(),
            'accounts' => AccountingAccount::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (AccountingAccount $account) => [
                    'id' => $account->id,
                    'label' => $account->code.' · '.$account->name,
                ])
                ->values()
                ->all(),
            'entries' => $entries->through(fn (AccountingLedgerEntry $entry) => [
                'id' => $entry->id,
                'entry_group_uuid' => $entry->entry_group_uuid,
                'transaction_date' => $entry->transaction_date?->toDateString(),
                'posting_reference' => $entry->posting_reference,
                'description' => $entry->description,
                'journal' => $entry->journal?->code,
                'account' => $entry->account
                    ? $entry->account->code.' · '.$entry->account->name
                    : null,
                'source_action' => $entry->source_action,
                'counterparty_name' => $entry->counterparty_name,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
            ]),
        ]);
    }
}
