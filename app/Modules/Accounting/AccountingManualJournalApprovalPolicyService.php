<?php

namespace App\Modules\Accounting;

use App\Core\Settings\SettingsService;

class AccountingManualJournalApprovalPolicyService
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

        return $amount >= $this->thresholdAmount($companyId);
    }

    public function thresholdAmount(string $companyId): float
    {
        $manualJournalThreshold = $this->settingsService->get(
            key: 'company.accounting.manual_journal_approval_threshold',
            default: null,
            companyId: $companyId,
        );

        if ($manualJournalThreshold !== null && $manualJournalThreshold !== '') {
            return (float) $manualJournalThreshold;
        }

        return (float) $this->settingsService->get(
            key: 'company.approvals.threshold_amount',
            default: 10000,
            companyId: $companyId,
        );
    }
}
