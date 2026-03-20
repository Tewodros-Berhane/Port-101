<?php

namespace App\Modules\Accounting;

use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingJournal;
use App\Modules\Accounting\Models\AccountingLedgerEntry;
use App\Modules\Accounting\Models\AccountingPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AccountingLedgerPostingService
{
    public function __construct(
        private readonly AccountingSetupService $setupService,
    ) {}

    public function postInvoice(AccountingInvoice $invoice, ?string $actorId = null): void
    {
        if ($this->hasSourceAction(
            companyId: (string) $invoice->company_id,
            sourceType: AccountingInvoice::class,
            sourceId: (string) $invoice->id,
            sourceAction: AccountingLedgerEntry::ACTION_INVOICE_POST,
        )) {
            return;
        }

        $invoice->loadMissing('company:id,currency_code', 'partner:id,name');

        $setup = $this->setupService->ensureCompanySetup(
            companyId: (string) $invoice->company_id,
            currencyCode: $invoice->currency_code ?: $invoice->company?->currency_code,
            actorId: $actorId,
        );

        $accounts = $setup['accounts'];
        $journal = $invoice->document_type === AccountingInvoice::TYPE_VENDOR_BILL
            ? $setup['journals'][AccountingJournal::SYSTEM_PURCHASE]
            : $setup['journals'][AccountingJournal::SYSTEM_SALES];

        $lines = $invoice->document_type === AccountingInvoice::TYPE_VENDOR_BILL
            ? [
                $this->entryLine($accounts[AccountingAccount::SYSTEM_PURCHASE_EXPENSE], (float) $invoice->subtotal, 0),
                $this->entryLine($accounts[AccountingAccount::SYSTEM_TAX_RECEIVABLE], (float) $invoice->tax_total, 0),
                $this->entryLine($accounts[AccountingAccount::SYSTEM_ACCOUNTS_PAYABLE], 0, (float) $invoice->grand_total),
            ]
            : [
                $this->entryLine($accounts[AccountingAccount::SYSTEM_ACCOUNTS_RECEIVABLE], (float) $invoice->grand_total, 0),
                $this->entryLine($accounts[AccountingAccount::SYSTEM_SALES_REVENUE], 0, (float) $invoice->subtotal),
                $this->entryLine($accounts[AccountingAccount::SYSTEM_SALES_TAX_PAYABLE], 0, (float) $invoice->tax_total),
            ];

        $this->createBatch(
            companyId: (string) $invoice->company_id,
            journal: $journal,
            sourceType: AccountingInvoice::class,
            sourceId: (string) $invoice->id,
            sourceAction: AccountingLedgerEntry::ACTION_INVOICE_POST,
            transactionDate: $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
            postingReference: (string) $invoice->invoice_number,
            description: $invoice->document_type === AccountingInvoice::TYPE_VENDOR_BILL
                ? 'Post vendor bill '.$invoice->invoice_number
                : 'Post customer invoice '.$invoice->invoice_number,
            counterpartyName: $invoice->partner?->name,
            currencyCode: $invoice->currency_code ?: $invoice->company?->currency_code,
            actorId: $actorId,
            lines: $lines,
            metadata: [
                'document_type' => $invoice->document_type,
                'status' => $invoice->status,
            ],
        );
    }

    public function reverseInvoice(AccountingInvoice $invoice, string $reason, ?string $actorId = null): void
    {
        if ($this->hasSourceAction(
            companyId: (string) $invoice->company_id,
            sourceType: AccountingInvoice::class,
            sourceId: (string) $invoice->id,
            sourceAction: AccountingLedgerEntry::ACTION_INVOICE_CANCEL,
        )) {
            return;
        }

        $entries = $this->sourceEntries(
            companyId: (string) $invoice->company_id,
            sourceType: AccountingInvoice::class,
            sourceId: (string) $invoice->id,
            sourceAction: AccountingLedgerEntry::ACTION_INVOICE_POST,
        );

        if ($entries->isEmpty()) {
            return;
        }

        $invoice->loadMissing('partner:id,name', 'company:id,currency_code');

        $this->createBatch(
            companyId: (string) $invoice->company_id,
            journal: $entries->first()->journal,
            sourceType: AccountingInvoice::class,
            sourceId: (string) $invoice->id,
            sourceAction: AccountingLedgerEntry::ACTION_INVOICE_CANCEL,
            transactionDate: now()->toDateString(),
            postingReference: (string) $invoice->invoice_number.'-CXL',
            description: 'Reverse invoice '.$invoice->invoice_number,
            counterpartyName: $invoice->partner?->name,
            currencyCode: $invoice->currency_code ?: $invoice->company?->currency_code,
            actorId: $actorId,
            lines: $entries
                ->map(fn (AccountingLedgerEntry $entry) => [
                    'account' => $entry->account,
                    'debit' => (float) $entry->credit,
                    'credit' => (float) $entry->debit,
                ])
                ->all(),
            metadata: [
                'reason' => $reason,
                'reversal_of' => AccountingLedgerEntry::ACTION_INVOICE_POST,
            ],
        );
    }

    public function postPayment(AccountingPayment $payment, ?string $actorId = null): void
    {
        if ($this->hasSourceAction(
            companyId: (string) $payment->company_id,
            sourceType: AccountingPayment::class,
            sourceId: (string) $payment->id,
            sourceAction: AccountingLedgerEntry::ACTION_PAYMENT_POST,
        )) {
            return;
        }

        $payment->loadMissing('company:id,currency_code', 'invoice.partner:id,name');

        $setup = $this->setupService->ensureCompanySetup(
            companyId: (string) $payment->company_id,
            currencyCode: $payment->invoice?->currency_code ?: $payment->company?->currency_code,
            actorId: $actorId,
        );

        $accounts = $setup['accounts'];
        $journal = $setup['journals'][AccountingJournal::SYSTEM_BANK];
        $invoice = $payment->invoice;

        if (! $invoice) {
            return;
        }

        $lines = $invoice->document_type === AccountingInvoice::TYPE_VENDOR_BILL
            ? [
                $this->entryLine($accounts[AccountingAccount::SYSTEM_ACCOUNTS_PAYABLE], (float) $payment->amount, 0),
                $this->entryLine($accounts[AccountingAccount::SYSTEM_CASH_BANK], 0, (float) $payment->amount),
            ]
            : [
                $this->entryLine($accounts[AccountingAccount::SYSTEM_CASH_BANK], (float) $payment->amount, 0),
                $this->entryLine($accounts[AccountingAccount::SYSTEM_ACCOUNTS_RECEIVABLE], 0, (float) $payment->amount),
            ];

        $this->createBatch(
            companyId: (string) $payment->company_id,
            journal: $journal,
            sourceType: AccountingPayment::class,
            sourceId: (string) $payment->id,
            sourceAction: AccountingLedgerEntry::ACTION_PAYMENT_POST,
            transactionDate: $payment->payment_date?->toDateString() ?? now()->toDateString(),
            postingReference: (string) $payment->payment_number,
            description: 'Post payment '.$payment->payment_number,
            counterpartyName: $invoice->partner?->name,
            currencyCode: $invoice->currency_code ?: $payment->company?->currency_code,
            actorId: $actorId,
            lines: $lines,
            metadata: [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'document_type' => $invoice->document_type,
                'method' => $payment->method,
            ],
        );
    }

    public function reversePayment(AccountingPayment $payment, string $reason, ?string $actorId = null): void
    {
        if ($this->hasSourceAction(
            companyId: (string) $payment->company_id,
            sourceType: AccountingPayment::class,
            sourceId: (string) $payment->id,
            sourceAction: AccountingLedgerEntry::ACTION_PAYMENT_REVERSE,
        )) {
            return;
        }

        $entries = $this->sourceEntries(
            companyId: (string) $payment->company_id,
            sourceType: AccountingPayment::class,
            sourceId: (string) $payment->id,
            sourceAction: AccountingLedgerEntry::ACTION_PAYMENT_POST,
        );

        if ($entries->isEmpty()) {
            return;
        }

        $payment->loadMissing('company:id,currency_code', 'invoice.partner:id,name');

        $this->createBatch(
            companyId: (string) $payment->company_id,
            journal: $entries->first()->journal,
            sourceType: AccountingPayment::class,
            sourceId: (string) $payment->id,
            sourceAction: AccountingLedgerEntry::ACTION_PAYMENT_REVERSE,
            transactionDate: now()->toDateString(),
            postingReference: (string) $payment->payment_number.'-REV',
            description: 'Reverse payment '.$payment->payment_number,
            counterpartyName: $payment->invoice?->partner?->name,
            currencyCode: $payment->invoice?->currency_code ?: $payment->company?->currency_code,
            actorId: $actorId,
            lines: $entries
                ->map(fn (AccountingLedgerEntry $entry) => [
                    'account' => $entry->account,
                    'debit' => (float) $entry->credit,
                    'credit' => (float) $entry->debit,
                ])
                ->all(),
            metadata: [
                'reason' => $reason,
                'reversal_of' => AccountingLedgerEntry::ACTION_PAYMENT_POST,
            ],
        );
    }

    /**
     * @param  array<int, array{account: AccountingAccount, debit: float, credit: float}>  $lines
     * @param  array<string, mixed>  $metadata
     */
    private function createBatch(
        string $companyId,
        AccountingJournal $journal,
        string $sourceType,
        string $sourceId,
        string $sourceAction,
        string $transactionDate,
        string $postingReference,
        string $description,
        ?string $counterpartyName,
        ?string $currencyCode,
        ?string $actorId,
        array $lines,
        array $metadata = [],
    ): void {
        $normalizedLines = collect($lines)
            ->filter(fn (array $line) => round((float) $line['debit'], 2) > 0 || round((float) $line['credit'], 2) > 0)
            ->values();

        $totalDebit = round((float) $normalizedLines->sum(fn (array $line) => $line['debit']), 2);
        $totalCredit = round((float) $normalizedLines->sum(fn (array $line) => $line['credit']), 2);

        if ($normalizedLines->count() < 2 || abs($totalDebit - $totalCredit) > 0.01) {
            abort(422, 'Ledger posting is not balanced.');
        }

        $groupUuid = (string) Str::uuid();

        foreach ($normalizedLines as $line) {
            AccountingLedgerEntry::create([
                'company_id' => $companyId,
                'journal_id' => $journal->id,
                'account_id' => $line['account']->id,
                'entry_group_uuid' => $groupUuid,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_action' => $sourceAction,
                'transaction_date' => $transactionDate,
                'posting_reference' => $postingReference,
                'description' => $description,
                'counterparty_name' => $counterpartyName,
                'debit' => round((float) $line['debit'], 2),
                'credit' => round((float) $line['credit'], 2),
                'currency_code' => $currencyCode,
                'metadata' => $metadata,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }
    }

    private function hasSourceAction(
        string $companyId,
        string $sourceType,
        string $sourceId,
        string $sourceAction,
    ): bool {
        return AccountingLedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('source_action', $sourceAction)
            ->exists();
    }

    /**
     * @return Collection<int, AccountingLedgerEntry>
     */
    private function sourceEntries(
        string $companyId,
        string $sourceType,
        string $sourceId,
        string $sourceAction,
    ): Collection {
        return AccountingLedgerEntry::query()
            ->with(['journal', 'account'])
            ->where('company_id', $companyId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('source_action', $sourceAction)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array{account: AccountingAccount, debit: float, credit: float}
     */
    private function entryLine(AccountingAccount $account, float $debit, float $credit): array
    {
        return [
            'account' => $account,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ];
    }
}
