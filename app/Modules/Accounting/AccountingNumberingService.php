<?php

namespace App\Modules\Accounting;

use App\Core\Settings\SettingsService;
use App\Modules\Accounting\Models\AccountingInvoice;

class AccountingNumberingService
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function nextInvoiceNumber(
        string $companyId,
        string $documentType = AccountingInvoice::TYPE_CUSTOMER_INVOICE,
        ?string $actorId = null
    ): string {
        [$prefixKey, $nextKey, $defaultPrefix] = $this->invoiceKeysForDocumentType($documentType);

        $prefix = (string) $this->settingsService->get(
            key: $prefixKey,
            default: $defaultPrefix,
            companyId: $companyId,
        );

        $next = (int) $this->settingsService->get(
            key: $nextKey,
            default: 1001,
            companyId: $companyId,
        );

        $this->settingsService->set(
            key: $nextKey,
            value: $next + 1,
            companyId: $companyId,
            actorId: $actorId,
        );

        return $this->formatNumber($prefix, $next);
    }

    public function nextPaymentNumber(string $companyId, ?string $actorId = null): string
    {
        $prefix = (string) $this->settingsService->get(
            key: 'company.numbering.payment_prefix',
            default: 'PAY',
            companyId: $companyId,
        );

        $next = (int) $this->settingsService->get(
            key: 'company.numbering.payment_next',
            default: 1001,
            companyId: $companyId,
        );

        $this->settingsService->set(
            key: 'company.numbering.payment_next',
            value: $next + 1,
            companyId: $companyId,
            actorId: $actorId,
        );

        return $this->formatNumber($prefix, $next);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function invoiceKeysForDocumentType(string $documentType): array
    {
        if ($documentType === AccountingInvoice::TYPE_VENDOR_BILL) {
            return [
                'company.numbering.vendor_bill_prefix',
                'company.numbering.vendor_bill_next',
                'BILL',
            ];
        }

        return [
            'company.numbering.invoice_prefix',
            'company.numbering.invoice_next',
            'INV',
        ];
    }

    private function formatNumber(string $prefix, int $number): string
    {
        return sprintf('%s-%06d', strtoupper(trim($prefix)), $number);
    }
}
