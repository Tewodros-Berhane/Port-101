<?php

namespace App\Modules\Accounting;

use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingJournal;

class AccountingSetupService
{
    /**
     * @return array{
     *     accounts: array<string, AccountingAccount>,
     *     journals: array<string, AccountingJournal>
     * }
     */
    public function ensureCompanySetup(
        string $companyId,
        ?string $currencyCode = null,
        ?string $actorId = null
    ): array {
        $accounts = [];

        foreach ($this->defaultAccounts() as $definition) {
            $account = AccountingAccount::query()
                ->where('company_id', $companyId)
                ->where('system_key', $definition['system_key'])
                ->first();

            if (! $account) {
                $account = AccountingAccount::create([
                    'company_id' => $companyId,
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'account_type' => $definition['account_type'],
                    'category' => $definition['category'],
                    'normal_balance' => $definition['normal_balance'],
                    'system_key' => $definition['system_key'],
                    'is_active' => true,
                    'is_system' => true,
                    'allows_manual_posting' => false,
                    'description' => $definition['description'],
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            }

            $accounts[$definition['system_key']] = $account;
        }

        $journals = [];

        foreach ($this->defaultJournals() as $definition) {
            $journal = AccountingJournal::query()
                ->where('company_id', $companyId)
                ->where('system_key', $definition['system_key'])
                ->first();

            if (! $journal) {
                $journal = AccountingJournal::create([
                    'company_id' => $companyId,
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'journal_type' => $definition['journal_type'],
                    'system_key' => $definition['system_key'],
                    'default_account_id' => $definition['default_account_key']
                        ? $accounts[$definition['default_account_key']]->id
                        : null,
                    'currency_code' => $currencyCode,
                    'is_active' => true,
                    'is_system' => true,
                    'description' => $definition['description'],
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);
            } elseif (
                $definition['default_account_key']
                && ! $journal->default_account_id
            ) {
                $journal->update([
                    'default_account_id' => $accounts[$definition['default_account_key']]->id,
                    'updated_by' => $actorId,
                ]);
            }

            $journals[$definition['system_key']] = $journal->fresh('defaultAccount');
        }

        return [
            'accounts' => $accounts,
            'journals' => $journals,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function defaultAccounts(): array
    {
        return [
            [
                'code' => '1000',
                'name' => 'Cash and Bank',
                'account_type' => AccountingAccount::TYPE_ASSET,
                'category' => AccountingAccount::CATEGORY_CASH,
                'normal_balance' => AccountingAccount::NORMAL_DEBIT,
                'system_key' => AccountingAccount::SYSTEM_CASH_BANK,
                'description' => 'Default liquidity account for payment postings.',
            ],
            [
                'code' => '1100',
                'name' => 'Accounts Receivable',
                'account_type' => AccountingAccount::TYPE_ASSET,
                'category' => AccountingAccount::CATEGORY_RECEIVABLE,
                'normal_balance' => AccountingAccount::NORMAL_DEBIT,
                'system_key' => AccountingAccount::SYSTEM_ACCOUNTS_RECEIVABLE,
                'description' => 'Customer receivables created from posted invoices.',
            ],
            [
                'code' => '1300',
                'name' => 'Tax Receivable',
                'account_type' => AccountingAccount::TYPE_ASSET,
                'category' => AccountingAccount::CATEGORY_TAX_ASSET,
                'normal_balance' => AccountingAccount::NORMAL_DEBIT,
                'system_key' => AccountingAccount::SYSTEM_TAX_RECEIVABLE,
                'description' => 'Recoverable input tax from vendor bills.',
            ],
            [
                'code' => '2000',
                'name' => 'Accounts Payable',
                'account_type' => AccountingAccount::TYPE_LIABILITY,
                'category' => AccountingAccount::CATEGORY_PAYABLE,
                'normal_balance' => AccountingAccount::NORMAL_CREDIT,
                'system_key' => AccountingAccount::SYSTEM_ACCOUNTS_PAYABLE,
                'description' => 'Vendor liabilities created from posted bills.',
            ],
            [
                'code' => '2200',
                'name' => 'Sales Tax Payable',
                'account_type' => AccountingAccount::TYPE_LIABILITY,
                'category' => AccountingAccount::CATEGORY_TAX_LIABILITY,
                'normal_balance' => AccountingAccount::NORMAL_CREDIT,
                'system_key' => AccountingAccount::SYSTEM_SALES_TAX_PAYABLE,
                'description' => 'Output tax collected on customer invoices.',
            ],
            [
                'code' => '3000',
                'name' => 'Retained Earnings',
                'account_type' => AccountingAccount::TYPE_EQUITY,
                'category' => AccountingAccount::CATEGORY_EQUITY,
                'normal_balance' => AccountingAccount::NORMAL_CREDIT,
                'system_key' => AccountingAccount::SYSTEM_RETAINED_EARNINGS,
                'description' => 'Default equity anchor for interim balance sheet reporting.',
            ],
            [
                'code' => '4000',
                'name' => 'Sales Revenue',
                'account_type' => AccountingAccount::TYPE_INCOME,
                'category' => AccountingAccount::CATEGORY_REVENUE,
                'normal_balance' => AccountingAccount::NORMAL_CREDIT,
                'system_key' => AccountingAccount::SYSTEM_SALES_REVENUE,
                'description' => 'Default revenue account for customer invoices.',
            ],
            [
                'code' => '5000',
                'name' => 'Purchase Expense',
                'account_type' => AccountingAccount::TYPE_EXPENSE,
                'category' => AccountingAccount::CATEGORY_EXPENSE,
                'normal_balance' => AccountingAccount::NORMAL_DEBIT,
                'system_key' => AccountingAccount::SYSTEM_PURCHASE_EXPENSE,
                'description' => 'Default expense account for vendor bills.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function defaultJournals(): array
    {
        return [
            [
                'code' => 'SAJ',
                'name' => 'Sales Journal',
                'journal_type' => AccountingJournal::TYPE_SALES,
                'system_key' => AccountingJournal::SYSTEM_SALES,
                'default_account_key' => null,
                'description' => 'Customer invoice postings.',
            ],
            [
                'code' => 'PUJ',
                'name' => 'Purchase Journal',
                'journal_type' => AccountingJournal::TYPE_PURCHASE,
                'system_key' => AccountingJournal::SYSTEM_PURCHASE,
                'default_account_key' => null,
                'description' => 'Vendor bill postings.',
            ],
            [
                'code' => 'BNK',
                'name' => 'Bank Journal',
                'journal_type' => AccountingJournal::TYPE_BANK,
                'system_key' => AccountingJournal::SYSTEM_BANK,
                'default_account_key' => AccountingAccount::SYSTEM_CASH_BANK,
                'description' => 'Incoming and outgoing payment postings.',
            ],
            [
                'code' => 'GEN',
                'name' => 'General Journal',
                'journal_type' => AccountingJournal::TYPE_GENERAL,
                'system_key' => AccountingJournal::SYSTEM_GENERAL,
                'default_account_key' => null,
                'description' => 'Reserved for future manual or adjustment entries.',
            ],
        ];
    }
}
