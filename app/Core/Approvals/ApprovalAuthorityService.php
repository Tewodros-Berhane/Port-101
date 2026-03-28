<?php

namespace App\Core\Approvals;

use App\Core\Approvals\Models\ApprovalAuthorityProfile;
use App\Core\Company\Models\Company;
use App\Models\User;

class ApprovalAuthorityService
{
    /**
     * @var array<string, int>
     */
    private const RISK_LEVEL_WEIGHTS = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    /**
     * Evaluate approval authority and SoD constraints for a workflow action.
     *
     * @param  array{
     *     requested_by_user_id?: string|null,
     *     amount?: int|float|string|null,
     *     risk_level?: string|null
     * }  $context
     */
    public function canApprove(
        Company $company,
        User $approver,
        string $module,
        string $action,
        array $context = []
    ): bool {
        $requesterId = $context['requested_by_user_id'] ?? null;
        $amount = $context['amount'] ?? null;
        $riskLevel = $context['risk_level'] ?? null;

        // Baseline SoD: requester cannot be final approver on critical approvals.
        if (
            $requesterId
            && (string) $requesterId === (string) $approver->id
            && in_array($action, ['po_final_approval', 'vendor_first_payment_approval', 'hr_leave_approval', 'hr_attendance_approval', 'hr_reimbursement_approval'], true)
        ) {
            return false;
        }

        // Period close must always be restricted to finance authority.
        if (
            $module === 'accounting'
            && $action === 'period_close'
            && ! $approver->hasPermission('accounting.period.close', $company)
        ) {
            return false;
        }

        $profile = $this->resolveProfile($company, $approver, $module, $action);

        if (! $profile || ! $profile->is_active) {
            return true;
        }

        if (
            $profile->requires_separate_requester
            && $requesterId
            && (string) $requesterId === (string) $approver->id
        ) {
            return false;
        }

        if ($amount !== null && $profile->max_amount !== null) {
            if ((float) $amount > (float) $profile->max_amount) {
                return false;
            }
        }

        if ($riskLevel && $profile->max_risk_level) {
            if ($this->riskLevelWeight($riskLevel) > $this->riskLevelWeight($profile->max_risk_level)) {
                return false;
            }
        }

        return true;
    }

    private function resolveProfile(
        Company $company,
        User $approver,
        string $module,
        string $action
    ): ?ApprovalAuthorityProfile {
        $userProfile = ApprovalAuthorityProfile::query()
            ->where('company_id', $company->id)
            ->where('module', $module)
            ->where('action', $action)
            ->where('user_id', $approver->id)
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        if ($userProfile) {
            return $userProfile;
        }

        $roleId = $approver->memberships()
            ->where('company_id', $company->id)
            ->value('role_id');

        if (! $roleId) {
            return null;
        }

        return ApprovalAuthorityProfile::query()
            ->where('company_id', $company->id)
            ->where('module', $module)
            ->where('action', $action)
            ->where('role_id', $roleId)
            ->whereNull('user_id')
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();
    }

    private function riskLevelWeight(string $level): int
    {
        return self::RISK_LEVEL_WEIGHTS[strtolower($level)] ?? 0;
    }
}
