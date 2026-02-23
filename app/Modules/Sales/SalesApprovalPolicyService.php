<?php

namespace App\Modules\Sales;

use App\Core\Settings\SettingsService;

class SalesApprovalPolicyService
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function requiresApproval(string $companyId, float $amount): bool
    {
        $enabled = (bool) $this->settingsService->get(
            key: 'company.approvals.enabled',
            default: false,
            companyId: $companyId,
        );

        if (! $enabled) {
            return false;
        }

        $policy = (string) $this->settingsService->get(
            key: 'company.approvals.policy',
            default: 'none',
            companyId: $companyId,
        );

        if ($policy === 'always') {
            return true;
        }

        if ($policy !== 'amount_based') {
            return false;
        }

        $threshold = (float) $this->settingsService->get(
            key: 'company.approvals.threshold_amount',
            default: 10000,
            companyId: $companyId,
        );

        return $amount >= $threshold;
    }
}


