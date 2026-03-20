<?php

namespace App\Modules\Accounting;

use App\Core\Company\Models\Company;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingLedgerEntry;
use App\Modules\Accounting\Models\AccountingPayment;

class AccountingLedgerBackfillService
{
    public function __construct(
        private readonly AccountingSetupService $setupService,
        private readonly AccountingLedgerPostingService $postingService,
    ) {}

    public function backfillCompany(string $companyId, ?string $actorId = null): void
    {
        $company = Company::query()->findOrFail($companyId);

        $this->setupService->ensureCompanySetup(
            companyId: $company->id,
            currencyCode: $company->currency_code,
            actorId: $actorId,
        );

        AccountingLedgerEntry::query()
            ->where('company_id', $companyId)
            ->whereIn('source_type', [
                AccountingInvoice::class,
                AccountingPayment::class,
            ])
            ->whereIn('source_action', [
                AccountingLedgerEntry::ACTION_INVOICE_POST,
                AccountingLedgerEntry::ACTION_INVOICE_CANCEL,
                AccountingLedgerEntry::ACTION_PAYMENT_POST,
                AccountingLedgerEntry::ACTION_PAYMENT_REVERSE,
            ])
            ->forceDelete();

        AccountingInvoice::withoutGlobalScopes()
            ->with(['company:id,currency_code', 'partner:id,name'])
            ->where('company_id', $companyId)
            ->orderBy('invoice_date')
            ->chunkById(100, function ($invoices) use ($actorId): void {
                foreach ($invoices as $invoice) {
                    if (
                        in_array($invoice->status, [
                            AccountingInvoice::STATUS_POSTED,
                            AccountingInvoice::STATUS_PARTIALLY_PAID,
                            AccountingInvoice::STATUS_PAID,
                        ], true)
                        || ($invoice->status === AccountingInvoice::STATUS_CANCELLED && $invoice->posted_at)
                    ) {
                        $this->postingService->postInvoice($invoice, $actorId);
                    }

                    if ($invoice->status === AccountingInvoice::STATUS_CANCELLED && $invoice->posted_at) {
                        $this->postingService->reverseInvoice(
                            invoice: $invoice,
                            reason: 'Historical cancellation backfill.',
                            actorId: $actorId,
                        );
                    }
                }
            }, 'id');

        AccountingPayment::withoutGlobalScopes()
            ->with(['company:id,currency_code', 'invoice.partner:id,name'])
            ->where('company_id', $companyId)
            ->orderBy('payment_date')
            ->chunkById(100, function ($payments) use ($actorId): void {
                foreach ($payments as $payment) {
                    if (
                        in_array($payment->status, [
                            AccountingPayment::STATUS_POSTED,
                            AccountingPayment::STATUS_RECONCILED,
                        ], true)
                        || ($payment->status === AccountingPayment::STATUS_REVERSED && $payment->posted_at)
                    ) {
                        $this->postingService->postPayment($payment, $actorId);
                    }

                    if ($payment->status === AccountingPayment::STATUS_REVERSED && $payment->posted_at) {
                        $this->postingService->reversePayment(
                            payment: $payment,
                            reason: $payment->reversal_reason ?: 'Historical reversal backfill.',
                            actorId: $actorId,
                        );
                    }
                }
            }, 'id');
    }
}
