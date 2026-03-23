<?php

namespace App\Modules\Inventory;

use App\Core\Settings\SettingsService;

class InventoryCycleCountApprovalPolicyService
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {}

    public function requiresApproval(
        string $companyId,
        float $absoluteVarianceQuantity,
        float $absoluteVarianceValue,
    ): bool {
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

        if ($absoluteVarianceValue >= $this->valueThreshold($companyId)) {
            return true;
        }

        $quantityThreshold = $this->quantityThreshold($companyId);

        return $quantityThreshold > 0 && $absoluteVarianceQuantity >= $quantityThreshold;
    }

    public function valueThreshold(string $companyId): float
    {
        return (float) $this->settingsService->get(
            key: 'company.inventory.cycle_count_approval_value_threshold',
            default: $this->settingsService->get(
                key: 'company.approvals.threshold_amount',
                default: 10000,
                companyId: $companyId,
            ),
            companyId: $companyId,
        );
    }

    public function quantityThreshold(string $companyId): float
    {
        return (float) $this->settingsService->get(
            key: 'company.inventory.cycle_count_approval_quantity_threshold',
            default: 25,
            companyId: $companyId,
        );
    }
}
