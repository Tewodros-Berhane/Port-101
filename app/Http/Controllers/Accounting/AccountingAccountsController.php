<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\AccountingSetupService;
use App\Modules\Accounting\AccountingStatementService;
use App\Modules\Accounting\Models\AccountingAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingAccountsController extends Controller
{
    public function index(
        Request $request,
        AccountingSetupService $setupService,
        AccountingStatementService $statementService,
    ): Response {
        abort_unless($request->user()?->hasPermission('accounting.accounts.view'), 403);

        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'account_type' => ['nullable', 'string', 'in:asset,liability,equity,income,expense'],
        ]);

        $setupService->ensureCompanySetup(
            companyId: $company->id,
            currencyCode: $company->currency_code,
            actorId: $request->user()?->id,
        );

        $accounts = AccountingAccount::query()
            ->withSum('ledgerEntries as debit_total', 'debit')
            ->withSum('ledgerEntries as credit_total', 'credit')
            ->withCount('ledgerEntries')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->where(function ($builder) use ($filters): void {
                    $term = '%'.trim((string) $filters['search']).'%';
                    $builder->where('code', 'like', $term)
                        ->orWhere('name', 'like', $term);
                })
            )
            ->when(
                filled($filters['account_type'] ?? null),
                fn ($query) => $query->where('account_type', $filters['account_type'])
            )
            ->orderBy('code')
            ->get();

        $statementSnapshot = $statementService->financialStatements(
            company: $company,
            startDate: now()->startOfMonth()->toDateString(),
            endDate: now()->toDateString(),
        )['snapshot'];

        return Inertia::render('accounting/accounts/index', [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'account_type' => $filters['account_type'] ?? '',
            ],
            'summary' => [
                'total_accounts' => $accounts->count(),
                'active_accounts' => $accounts->where('is_active', true)->count(),
                'system_accounts' => $accounts->where('is_system', true)->count(),
                'cash_balance' => (float) $statementSnapshot['cash_balance'],
            ],
            'accounts' => $accounts->map(function (AccountingAccount $account) {
                $debitTotal = round((float) ($account->getAttribute('debit_total') ?? 0), 2);
                $creditTotal = round((float) ($account->getAttribute('credit_total') ?? 0), 2);
                $balance = $account->normal_balance === AccountingAccount::NORMAL_DEBIT
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'account_type' => $account->account_type,
                    'category' => $account->category,
                    'normal_balance' => $account->normal_balance,
                    'is_active' => (bool) $account->is_active,
                    'is_system' => (bool) $account->is_system,
                    'entry_count' => (int) ($account->ledger_entries_count ?? 0),
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'balance' => $balance,
                ];
            })->values()->all(),
        ]);
    }
}
