<?php

namespace App\Modules\Accounting;

use App\Core\Company\Models\Company;
use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AccountingStatementService
{
    public function __construct(
        private readonly AccountingSetupService $setupService,
    ) {}

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     profit_and_loss: array<string, mixed>,
     *     balance_sheet: array<string, mixed>,
     *     cash_flow: array<string, mixed>,
     *     trial_balance: array<string, mixed>,
     *     snapshot: array<string, float>
     * }
     */
    public function financialStatements(
        Company $company,
        string $startDate,
        string $endDate,
    ): array {
        $this->setupService->ensureCompanySetup(
            companyId: (string) $company->id,
            currencyCode: $company->currency_code,
        );

        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();

        $periodBalances = $this->ledgerBalances(
            companyId: (string) $company->id,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
        );

        $endingBalances = $this->ledgerBalances(
            companyId: (string) $company->id,
            startDate: null,
            endDate: $end->toDateString(),
        );

        $openingCashBalances = $this->ledgerBalances(
            companyId: (string) $company->id,
            startDate: null,
            endDate: $start->subDay()->toDateString(),
        );

        $accounts = AccountingAccount::query()
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get();

        $profitAndLoss = $this->profitAndLoss($accounts, $periodBalances);
        $balanceSheet = $this->balanceSheet($accounts, $endingBalances);
        $cashFlow = $this->cashFlow($accounts, $periodBalances, $openingCashBalances);
        $trialBalance = $this->trialBalance($accounts, $endingBalances);

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'profit_and_loss' => $profitAndLoss,
            'balance_sheet' => $balanceSheet,
            'cash_flow' => $cashFlow,
            'trial_balance' => $trialBalance,
            'snapshot' => [
                'revenue' => $profitAndLoss['total_revenue'],
                'expenses' => $profitAndLoss['total_expenses'],
                'net_income' => $profitAndLoss['net_income'],
                'assets' => $balanceSheet['total_assets'],
                'liabilities' => $balanceSheet['total_liabilities'],
                'equity' => $balanceSheet['total_equity'],
                'cash_balance' => $cashFlow['closing_cash_balance'],
            ],
        ];
    }

    /**
     * @param  Collection<int, AccountingAccount>  $accounts
     * @param  Collection<string, array{debit_total: float, credit_total: float}>  $balances
     * @return array<string, mixed>
     */
    private function profitAndLoss(Collection $accounts, Collection $balances): array
    {
        $revenueRows = [];
        $expenseRows = [];
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($accounts as $account) {
            if (! in_array($account->account_type, [
                AccountingAccount::TYPE_INCOME,
                AccountingAccount::TYPE_EXPENSE,
            ], true)) {
                continue;
            }

            $balance = $this->accountBalance(
                account: $account,
                debitTotal: (float) ($balances[$account->id]['debit_total'] ?? 0),
                creditTotal: (float) ($balances[$account->id]['credit_total'] ?? 0),
            );

            if (abs($balance) < 0.01) {
                continue;
            }

            $row = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'amount' => round($balance, 2),
            ];

            if ($account->account_type === AccountingAccount::TYPE_INCOME) {
                $revenueRows[] = $row;
                $totalRevenue += $balance;
            } else {
                $expenseRows[] = $row;
                $totalExpenses += $balance;
            }
        }

        return [
            'revenue' => $revenueRows,
            'expenses' => $expenseRows,
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_income' => round($totalRevenue - $totalExpenses, 2),
        ];
    }

    /**
     * @param  Collection<int, AccountingAccount>  $accounts
     * @param  Collection<string, array{debit_total: float, credit_total: float}>  $balances
     * @return array<string, mixed>
     */
    private function balanceSheet(Collection $accounts, Collection $balances): array
    {
        $assetRows = [];
        $liabilityRows = [];
        $equityRows = [];
        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;
        $currentEarnings = 0.0;

        foreach ($accounts as $account) {
            $balance = $this->accountBalance(
                account: $account,
                debitTotal: (float) ($balances[$account->id]['debit_total'] ?? 0),
                creditTotal: (float) ($balances[$account->id]['credit_total'] ?? 0),
            );

            if (abs($balance) < 0.01) {
                continue;
            }

            $row = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'amount' => round($balance, 2),
            ];

            if ($account->account_type === AccountingAccount::TYPE_ASSET) {
                $assetRows[] = $row;
                $totalAssets += $balance;

                continue;
            }

            if ($account->account_type === AccountingAccount::TYPE_LIABILITY) {
                $liabilityRows[] = $row;
                $totalLiabilities += $balance;

                continue;
            }

            if ($account->account_type === AccountingAccount::TYPE_EQUITY) {
                $equityRows[] = $row;
                $totalEquity += $balance;

                continue;
            }

            if ($account->account_type === AccountingAccount::TYPE_INCOME) {
                $currentEarnings += $balance;
            } elseif ($account->account_type === AccountingAccount::TYPE_EXPENSE) {
                $currentEarnings -= $balance;
            }
        }

        if (abs($currentEarnings) >= 0.01) {
            $equityRows[] = [
                'account_id' => 'current_earnings',
                'code' => '9999',
                'name' => 'Current Earnings',
                'amount' => round($currentEarnings, 2),
            ];
            $totalEquity += $currentEarnings;
        }

        return [
            'assets' => $assetRows,
            'liabilities' => $liabilityRows,
            'equity' => $equityRows,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'balance_gap' => round($totalAssets - ($totalLiabilities + $totalEquity), 2),
        ];
    }

    /**
     * @param  Collection<int, AccountingAccount>  $accounts
     * @param  Collection<string, array{debit_total: float, credit_total: float}>  $periodBalances
     * @param  Collection<string, array{debit_total: float, credit_total: float}>  $openingBalances
     * @return array<string, mixed>
     */
    private function cashFlow(
        Collection $accounts,
        Collection $periodBalances,
        Collection $openingBalances,
    ): array {
        $cashAccounts = $accounts
            ->filter(fn (AccountingAccount $account) => $account->category === AccountingAccount::CATEGORY_CASH)
            ->values();

        $openingCash = 0.0;
        $cashInflows = 0.0;
        $cashOutflows = 0.0;

        foreach ($cashAccounts as $account) {
            $openingCash += $this->accountBalance(
                account: $account,
                debitTotal: (float) ($openingBalances[$account->id]['debit_total'] ?? 0),
                creditTotal: (float) ($openingBalances[$account->id]['credit_total'] ?? 0),
            );

            $cashInflows += (float) ($periodBalances[$account->id]['debit_total'] ?? 0);
            $cashOutflows += (float) ($periodBalances[$account->id]['credit_total'] ?? 0);
        }

        $netCashMovement = round($cashInflows - $cashOutflows, 2);

        return [
            'opening_cash_balance' => round($openingCash, 2),
            'cash_inflows' => round($cashInflows, 2),
            'cash_outflows' => round($cashOutflows, 2),
            'net_cash_movement' => $netCashMovement,
            'closing_cash_balance' => round($openingCash + $netCashMovement, 2),
        ];
    }

    /**
     * @param  Collection<int, AccountingAccount>  $accounts
     * @param  Collection<string, array{debit_total: float, credit_total: float}>  $balances
     * @return array<string, mixed>
     */
    private function trialBalance(Collection $accounts, Collection $balances): array
    {
        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $debit = round((float) ($balances[$account->id]['debit_total'] ?? 0), 2);
            $credit = round((float) ($balances[$account->id]['credit_total'] ?? 0), 2);

            if ($debit === 0.0 && $credit === 0.0) {
                continue;
            }

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
                'debit' => $debit,
                'credit' => $credit,
                'net_balance' => round($this->accountBalance($account, $debit, $credit), 2),
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [
            'rows' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'out_of_balance' => round($totalDebit - $totalCredit, 2),
        ];
    }

    /**
     * @return Collection<string, array{debit_total: float, credit_total: float}>
     */
    private function ledgerBalances(
        string $companyId,
        ?string $startDate,
        ?string $endDate,
    ): Collection {
        $query = AccountingLedgerEntry::query()
            ->where('company_id', $companyId);

        if ($startDate) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }

        return $query
            ->selectRaw('account_id, SUM(debit) as debit_total, SUM(credit) as credit_total')
            ->groupBy('account_id')
            ->get()
            ->mapWithKeys(fn (AccountingLedgerEntry $entry) => [
                (string) $entry->account_id => [
                    'debit_total' => round((float) $entry->getAttribute('debit_total'), 2),
                    'credit_total' => round((float) $entry->getAttribute('credit_total'), 2),
                ],
            ]);
    }

    private function accountBalance(
        AccountingAccount $account,
        float $debitTotal,
        float $creditTotal,
    ): float {
        return $account->normal_balance === AccountingAccount::NORMAL_DEBIT
            ? round($debitTotal - $creditTotal, 2)
            : round($creditTotal - $debitTotal, 2);
    }
}
