<?php

namespace App\Modules\Accounting;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingBankReconciliationBatch;
use App\Modules\Accounting\Models\AccountingBankReconciliationItem;
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

            $reconciledAt = now();

            $batch = AccountingBankReconciliationBatch::create([
                'company_id' => $companyId,
                'journal_id' => $journal->id,
                'statement_reference' => $attributes['statement_reference'],
                'statement_date' => $attributes['statement_date'],
                'notes' => $attributes['notes'] ?? null,
                'reconciled_by' => $actor?->id,
                'reconciled_at' => $reconciledAt,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            foreach ($paymentIds as $paymentId) {
                /** @var AccountingPayment $payment */
                $payment = $payments[$paymentId];
                $this->assertPaymentCanBeBankReconciled($payment);

                AccountingBankReconciliationItem::create([
                    'company_id' => $companyId,
                    'batch_id' => $batch->id,
                    'payment_id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'created_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]);

                $payment->update([
                    'bank_reconciled_at' => $reconciledAt,
                    'bank_reconciled_by' => $actor?->id,
                    'updated_by' => $actor?->id,
                ]);

                AccountingLedgerEntry::query()
                    ->where('company_id', $companyId)
                    ->where('source_type', AccountingPayment::class)
                    ->where('source_id', $payment->id)
                    ->where('source_action', AccountingLedgerEntry::ACTION_PAYMENT_POST)
                    ->update([
                        'reconciled_at' => $reconciledAt,
                        'updated_by' => $actor?->id,
                        'updated_at' => $reconciledAt,
                    ]);
            }

            return $batch->fresh([
                'journal',
                'items.payment.invoice.partner',
            ]);
        });
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
