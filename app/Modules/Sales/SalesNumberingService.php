<?php

namespace App\Modules\Sales;

use App\Core\Settings\SettingsService;

class SalesNumberingService
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function nextQuoteNumber(string $companyId, ?string $actorId = null): string
    {
        $prefix = (string) $this->settingsService->get(
            key: 'company.numbering.sales_quote_prefix',
            default: 'SQ',
            companyId: $companyId,
        );

        $next = (int) $this->settingsService->get(
            key: 'company.numbering.sales_quote_next',
            default: 1001,
            companyId: $companyId,
        );

        $this->settingsService->set(
            key: 'company.numbering.sales_quote_next',
            value: $next + 1,
            companyId: $companyId,
            actorId: $actorId,
        );

        return $this->formatNumber($prefix, $next);
    }

    public function nextOrderNumber(string $companyId, ?string $actorId = null): string
    {
        $prefix = (string) $this->settingsService->get(
            key: 'company.numbering.sales_order_prefix',
            default: 'SO',
            companyId: $companyId,
        );

        $next = (int) $this->settingsService->get(
            key: 'company.numbering.sales_order_next',
            default: 1001,
            companyId: $companyId,
        );

        $this->settingsService->set(
            key: 'company.numbering.sales_order_next',
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


