<?php

namespace App\Modules\Purchasing;

use App\Core\Settings\SettingsService;

class PurchasingNumberingService
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function nextRfqNumber(string $companyId, ?string $actorId = null): string
    {
        $prefix = (string) $this->settingsService->get(
            key: 'company.numbering.purchase_rfq_prefix',
            default: 'RFQ',
            companyId: $companyId,
        );

        $next = (int) $this->settingsService->get(
            key: 'company.numbering.purchase_rfq_next',
            default: 1001,
            companyId: $companyId,
        );

        $this->settingsService->set(
            key: 'company.numbering.purchase_rfq_next',
            value: $next + 1,
            companyId: $companyId,
            actorId: $actorId,
        );

        return $this->formatNumber($prefix, $next);
    }

    public function nextOrderNumber(string $companyId, ?string $actorId = null): string
    {
        $prefix = (string) $this->settingsService->get(
            key: 'company.numbering.purchase_order_prefix',
            default: 'PO',
            companyId: $companyId,
        );

        $next = (int) $this->settingsService->get(
            key: 'company.numbering.purchase_order_next',
            default: 1001,
            companyId: $companyId,
        );

        $this->settingsService->set(
            key: 'company.numbering.purchase_order_next',
            value: $next + 1,
            companyId: $companyId,
            actorId: $actorId,
        );

        return $this->formatNumber($prefix, $next);
    }

    private function formatNumber(string $prefix, int $number): string
    {
        return sprintf('%s-%06d', strtoupper(trim($prefix)), $number);
    }
}
