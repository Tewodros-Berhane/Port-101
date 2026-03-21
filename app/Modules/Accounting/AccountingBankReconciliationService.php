<?php

namespace App\Modules\Accounting;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingBankReconciliationBatch;
use App\Modules\Accounting\Models\AccountingBankReconciliationItem;
use App\Modules\Accounting\Models\AccountingBankStatementImport;
use App\Modules\Accounting\Models\AccountingBankStatementImportLine;
use App\Modules\Accounting\Models\AccountingJournal;
use App\Modules\Accounting\Models\AccountingLedgerEntry;
use App\Modules\Accounting\Models\AccountingPayment;
use Illuminate\Support\Facades\DB;

class AccountingBankReconciliationService
{
    /**
     * @param  array{
     *     journal_id: string,
     *     statement_reference: string,
     *     statement_date: string,
     *     notes?: string|null,
     *     payment_ids: array<int, string>
     * }  $attributes
     */
    public function createBatch(
        array $attributes,
        string $companyId,
        ?User $actor = null,
    ): AccountingBankReconciliationBatch {
        return DB::transaction(function () use ($attributes, $companyId, $actor) {
            $journal = $this->resolveJournal((string) $attributes['journal_id'], $companyId);
            $paymentIds = collect($attributes['payment_ids'])
                ->map(fn ($paymentId) => (string) $paymentId)
                ->unique()
                ->values();

            $payments = AccountingPayment::query()
                ->with(['invoice:id,invoice_number,partner_id', 'invoice.partner:id,name'])
                ->lockForUpdate()
                ->where('company_id', $companyId)
                ->whereIn('id', $paymentIds)
                ->when($actor, fn ($builder) => $actor->applyDataScopeToQuery($builder))
                ->get()
                ->keyBy('id');

            if ($payments->count() !== $paymentIds->count()) {
                abort(422, 'One or more selected payments could not be loaded for reconciliation.');
            }

            $batch = $this->createBatchRecord(
                companyId: $companyId,
                journalId: (string) $journal->id,
                statementReference: (string) $attributes['statement_reference'],
                statementDate: (string) $attributes['statement_date'],
                notes: $attributes['notes'] ?? null,
                actor: $actor,
            );

            foreach ($paymentIds as $paymentId) {
                $payment = $payments[$paymentId];

                $this->appendPaymentToBatch(
                    batch: $batch,
                    payment: $payment,
                    actor: $actor,
                    statementLineDate: null,
                    statementLineReference: null,
                    statementLineDescription: null,
                );
            }

            return $batch->fresh(['journal', 'items.payment.invoice.partner']);
        });
    }

    /**
     * @param  array<int, string>  $lineIds
     */
    public function createBatchFromImport(
        AccountingBankStatementImport $statementImport,
        array $lineIds,
        ?User $actor = null,
    ): AccountingBankReconciliationBatch {
        return DB::transaction(function () use ($statementImport, $lineIds, $actor) {
            $statementImport = AccountingBankStatementImport::query()
                ->with([
                    'lines.payment.invoice.partner',
                    'journal',
                ])
                ->lockForUpdate()
                ->findOrFail($statementImport->id);

            if ($statementImport->reconciled_batch_id) {
                abort(422, 'This bank statement import has already been reconciled.');
            }

            $selectedLineIds = collect($lineIds)
                ->map(fn ($lineId) => (string) $lineId)
                ->unique()
                ->values();

            $lines = $statementImport->lines
                ->whereIn('id', $selectedLineIds)
                ->values();

            if ($lines->count() !== $selectedLineIds->count()) {
                abort(422, 'One or more selected statement lines could not be loaded.');
            }

            $batch = $this->createBatchRecord(
                companyId: (string) $statementImport->company_id,
                journalId: (string) $statementImport->journal_id,
                statementReference: (string) $statementImport->statement_reference,
                statementDate: $statementImport->statement_date?->toDateString() ?? now()->toDateString(),
                notes: $statementImport->notes,
                actor: $actor,
            );

            foreach ($lines as $line) {
                if ($line->match_status !== AccountingBankStatementImportLine::MATCH_STATUS_MATCHED) {
                    abort(422, 'Only matched statement lines can be reconciled.');
                }

                if (! $line->payment) {
                    abort(422, 'Matched statement line is missing its payment link.');
                }

                $this->appendPaymentToBatch(
                    batch: $batch,
                    payment: $line->payment,
                    actor: $actor,
                    statementLineDate: $line->transaction_date?->toDateString(),
                    statementLineReference: $line->reference,
                    statementLineDescription: $line->description,
                );
            }

            $statementImport->update([
                'reconciled_batch_id' => $batch->id,
                'updated_by' => $actor?->id,
            ]);

            return $batch->fresh(['journal', 'items.payment.invoice.partner']);
        });
    }

    public function unreconcileBatch(
        AccountingBankReconciliationBatch $batch,
        string $reason,
        ?User $actor = null,
    ): AccountingBankReconciliationBatch {
        return DB::transaction(function () use ($batch, $reason, $actor) {
            $batch = AccountingBankReconciliationBatch::query()
                ->with(['items.payment'])
                ->lockForUpdate()
                ->findOrFail($batch->id);

            if (! $batch->reconciled_at) {
                abort(422, 'Only reconciled bank batches can be unreconciled.');
            }

            if ($batch->unreconciled_at) {
                return $batch;
            }

            $unreconciledAt = now();

            foreach ($batch->items as $item) {
                $payment = $item->payment;

                if (! $payment) {
                    continue;
                }

                $payment->update([
                    'bank_reconciled_at' => null,
                    'bank_reconciled_by' => null,
                    'updated_by' => $actor?->id,
                ]);

                AccountingLedgerEntry::query()
                    ->where('company_id', $batch->company_id)
                    ->where('source_type', AccountingPayment::class)
                    ->where('source_id', $payment->id)
                    ->where('source_action', AccountingLedgerEntry::ACTION_PAYMENT_POST)
                    ->update([
                        'reconciled_at' => null,
                        'updated_by' => $actor?->id,
                        'updated_at' => $unreconciledAt,
                    ]);
            }

            AccountingBankStatementImport::query()
                ->where('company_id', $batch->company_id)
                ->where('reconciled_batch_id', $batch->id)
                ->update([
                    'reconciled_batch_id' => null,
                    'updated_by' => $actor?->id,
                    'updated_at' => $unreconciledAt,
                ]);

            $batch->update([
                'unreconciled_at' => $unreconciledAt,
                'unreconciled_by' => $actor?->id,
                'unreconcile_reason' => $reason,
                'updated_by' => $actor?->id,
            ]);

            return $batch->fresh([
                'journal',
                'items.payment.invoice.partner',
                'reconciledBy:id,name',
                'unreconciledBy:id,name',
            ]);
        });
    }

    private function createBatchRecord(
        string $companyId,
        string $journalId,
        string $statementReference,
        string $statementDate,
        ?string $notes,
        ?User $actor = null,
    ): AccountingBankReconciliationBatch {
        return AccountingBankReconciliationBatch::create([
            'company_id' => $companyId,
            'journal_id' => $journalId,
            'statement_reference' => $statementReference,
            'statement_date' => $statementDate,
            'notes' => $notes,
            'reconciled_by' => $actor?->id,
            'reconciled_at' => now(),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    private function appendPaymentToBatch(
        AccountingBankReconciliationBatch $batch,
        AccountingPayment $payment,
        ?User $actor = null,
        ?string $statementLineDate = null,
        ?string $statementLineReference = null,
        ?string $statementLineDescription = null,
    ): void {
        $this->assertPaymentCanBeBankReconciled($payment);

        $reconciledAt = $batch->reconciled_at ?? now();

        AccountingBankReconciliationItem::create([
            'company_id' => (string) $batch->company_id,
            'batch_id' => $batch->id,
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'statement_line_date' => $statementLineDate,
            'statement_line_reference' => $statementLineReference,
            'statement_line_description' => $statementLineDescription,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        $payment->update([
            'bank_reconciled_at' => $reconciledAt,
            'bank_reconciled_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        AccountingLedgerEntry::query()
            ->where('company_id', $batch->company_id)
            ->where('source_type', AccountingPayment::class)
            ->where('source_id', $payment->id)
            ->where('source_action', AccountingLedgerEntry::ACTION_PAYMENT_POST)
            ->update([
                'reconciled_at' => $reconciledAt,
                'updated_by' => $actor?->id,
                'updated_at' => $reconciledAt,
            ]);
    }

    private function resolveJournal(string $journalId, string $companyId): AccountingJournal
    {
        $journal = AccountingJournal::query()
            ->where('company_id', $companyId)
            ->findOrFail($journalId);

        if ($journal->journal_type !== AccountingJournal::TYPE_BANK) {
            abort(422, 'Bank reconciliation batches must use a bank journal.');
        }

        if (! $journal->is_active) {
            abort(422, 'Selected journal is inactive.');
        }

        return $journal;
    }

    private function assertPaymentCanBeBankReconciled(AccountingPayment $payment): void
    {
        if (! in_array($payment->status, [
            AccountingPayment::STATUS_POSTED,
            AccountingPayment::STATUS_RECONCILED,
        ], true)) {
            abort(422, 'Only posted payments can be bank reconciled.');
        }

        if (! $payment->posted_at) {
            abort(422, 'Payment must be posted before bank reconciliation.');
        }

        if ($payment->bank_reconciled_at) {
            abort(422, 'Selected payment is already bank reconciled.');
        }

        $hasLedgerEntries = AccountingLedgerEntry::query()
            ->where('company_id', $payment->company_id)
            ->where('source_type', AccountingPayment::class)
            ->where('source_id', $payment->id)
            ->where('source_action', AccountingLedgerEntry::ACTION_PAYMENT_POST)
            ->exists();

        if (! $hasLedgerEntries) {
            abort(422, 'Payment cannot be bank reconciled until its ledger entries exist.');
        }
    }
}
