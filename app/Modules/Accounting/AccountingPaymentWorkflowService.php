<?php

namespace App\Modules\Accounting;

use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Accounting\Models\AccountingReconciliationEntry;
use Illuminate\Support\Facades\DB;

class AccountingPaymentWorkflowService
{
    public function __construct(
        private readonly AccountingNumberingService $numberingService,
        private readonly AccountingPeriodGuardService $periodGuardService,
        private readonly AccountingInvoiceWorkflowService $invoiceWorkflowService,
    ) {}

    /**
     * @param  array{
     *     invoice_id: string,
     *     payment_date: string,
     *     amount: int|float|string,
     *     method?: string|null,
     *     reference?: string|null,
     *     notes?: string|null
     * }  $attributes
     */
    public function createDraft(
        array $attributes,
        string $companyId,
        ?string $actorId = null
    ): AccountingPayment {
        return DB::transaction(function () use ($attributes, $companyId, $actorId) {
            $invoice = AccountingInvoice::query()
                ->where('company_id', $companyId)
                ->findOrFail($attributes['invoice_id']);

            $this->assertInvoiceCanAcceptPayment($invoice);

            $amount = round((float) $attributes['amount'], 2);
            $this->assertPaymentAmountWithinBalance($invoice, $amount);

            return AccountingPayment::create([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'payment_number' => $this->numberingService->nextPaymentNumber(
                    companyId: $companyId,
                    actorId: $actorId,
                ),
                'status' => AccountingPayment::STATUS_DRAFT,
                'payment_date' => $attributes['payment_date'],
                'amount' => $amount,
                'method' => $attributes['method'] ?? null,
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        });
    }

    /**
     * @param  array{
     *     invoice_id: string,
     *     payment_date: string,
     *     amount: int|float|string,
     *     method?: string|null,
     *     reference?: string|null,
     *     notes?: string|null
     * }  $attributes
     */
    public function updateDraft(
        AccountingPayment $payment,
        array $attributes,
        ?string $actorId = null
    ): AccountingPayment {
        return DB::transaction(function () use ($payment, $attributes, $actorId) {
            $payment = AccountingPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== AccountingPayment::STATUS_DRAFT) {
                abort(422, 'Only draft payments can be updated.');
            }

            $invoice = AccountingInvoice::query()
                ->where('company_id', $payment->company_id)
                ->findOrFail($attributes['invoice_id']);

            $this->assertInvoiceCanAcceptPayment($invoice);

            $amount = round((float) $attributes['amount'], 2);
            $this->assertPaymentAmountWithinBalance($invoice, $amount);

            $payment->update([
                'invoice_id' => $invoice->id,
                'payment_date' => $attributes['payment_date'],
                'amount' => $amount,
                'method' => $attributes['method'] ?? null,
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'updated_by' => $actorId,
            ]);

            return $payment->fresh();
        });
    }

    public function post(AccountingPayment $payment, ?string $actorId = null): AccountingPayment
    {
        return DB::transaction(function () use ($payment, $actorId) {
            $payment = AccountingPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if (in_array($payment->status, [
                AccountingPayment::STATUS_POSTED,
                AccountingPayment::STATUS_RECONCILED,
            ], true)) {
                return $payment;
            }

            if ($payment->status === AccountingPayment::STATUS_REVERSED) {
                abort(422, 'Reversed payments cannot be posted.');
            }

            $invoice = AccountingInvoice::query()
                ->lockForUpdate()
                ->where('company_id', $payment->company_id)
                ->findOrFail($payment->invoice_id);

            $this->assertInvoiceCanAcceptPayment($invoice);
            $this->assertPaymentAmountWithinBalance($invoice, (float) $payment->amount);

            $this->periodGuardService->assertPostingAllowed(
                companyId: (string) $payment->company_id,
                postingDate: $payment->payment_date?->toDateString() ?? now()->toDateString(),
            );

            $payment->update([
                'status' => AccountingPayment::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $payment->fresh();
        });
    }

    public function reconcile(AccountingPayment $payment, ?string $actorId = null): AccountingPayment
    {
        return DB::transaction(function () use ($payment, $actorId) {
            $payment = AccountingPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === AccountingPayment::STATUS_RECONCILED) {
                return $payment;
            }

            if ($payment->status !== AccountingPayment::STATUS_POSTED) {
                abort(422, 'Only posted payments can be reconciled.');
            }

            $invoice = AccountingInvoice::query()
                ->lockForUpdate()
                ->where('company_id', $payment->company_id)
                ->findOrFail($payment->invoice_id);

            $appliedAlready = (float) $payment->reconciliationEntries()
                ->where('entry_type', AccountingReconciliationEntry::TYPE_APPLY)
                ->sum('amount');

            if ($appliedAlready > 0) {
                abort(422, 'Payment is already reconciled.');
            }

            $amount = round((float) $payment->amount, 2);
            $this->assertPaymentAmountWithinBalance($invoice, $amount);

            AccountingReconciliationEntry::create([
                'company_id' => (string) $payment->company_id,
                'invoice_id' => (string) $invoice->id,
                'payment_id' => (string) $payment->id,
                'entry_type' => AccountingReconciliationEntry::TYPE_APPLY,
                'amount' => $amount,
                'reconciled_at' => now(),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->invoiceWorkflowService->applyReconciledAmount($invoice, $amount, $actorId);

            $payment->update([
                'status' => AccountingPayment::STATUS_RECONCILED,
                'reconciled_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $payment->fresh();
        });
    }

    public function reverse(
        AccountingPayment $payment,
        string $reason,
        ?string $actorId = null
    ): AccountingPayment {
        return DB::transaction(function () use ($payment, $reason, $actorId) {
            $payment = AccountingPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === AccountingPayment::STATUS_REVERSED) {
                return $payment;
            }

            if (! in_array($payment->status, [
                AccountingPayment::STATUS_DRAFT,
                AccountingPayment::STATUS_POSTED,
                AccountingPayment::STATUS_RECONCILED,
            ], true)) {
                abort(422, 'This payment status cannot be reversed.');
            }

            $invoice = AccountingInvoice::query()
                ->lockForUpdate()
                ->where('company_id', $payment->company_id)
                ->findOrFail($payment->invoice_id);

            $appliedAmount = (float) $payment->reconciliationEntries()
                ->where('entry_type', AccountingReconciliationEntry::TYPE_APPLY)
                ->sum('amount');

            if ($appliedAmount > 0) {
                AccountingReconciliationEntry::create([
                    'company_id' => (string) $payment->company_id,
                    'invoice_id' => (string) $invoice->id,
                    'payment_id' => (string) $payment->id,
                    'entry_type' => AccountingReconciliationEntry::TYPE_REVERSAL,
                    'amount' => -$appliedAmount,
                    'reconciled_at' => now(),
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                $this->invoiceWorkflowService->reverseReconciledAmount($invoice, $appliedAmount, $actorId);
            }

            $payment->update([
                'status' => AccountingPayment::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by' => $actorId,
                'reversal_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            return $payment->fresh();
        });
    }

    private function assertInvoiceCanAcceptPayment(AccountingInvoice $invoice): void
    {
        if ($invoice->status === AccountingInvoice::STATUS_CANCELLED) {
            abort(422, 'Cancelled invoices cannot receive payments.');
        }

        if (! in_array($invoice->status, [
            AccountingInvoice::STATUS_POSTED,
            AccountingInvoice::STATUS_PARTIALLY_PAID,
            AccountingInvoice::STATUS_PAID,
        ], true)) {
            abort(422, 'Invoice must be posted before payments can be captured.');
        }
    }

    private function assertPaymentAmountWithinBalance(AccountingInvoice $invoice, float $amount): void
    {
        if ($amount <= 0) {
            abort(422, 'Payment amount must be greater than zero.');
        }

        $balanceDue = round((float) $invoice->balance_due, 2);

        if ($balanceDue <= 0) {
            abort(422, 'Invoice has no outstanding balance.');
        }

        if ($amount > ($balanceDue + 0.01)) {
            abort(422, 'Payment amount cannot exceed outstanding balance.');
        }
    }
}
