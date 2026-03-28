<?php

namespace App\Modules\Hr;

use App\Core\MasterData\Models\Currency;
use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrReimbursementCategory;
use App\Modules\Hr\Models\HrReimbursementClaim;
use App\Modules\Projects\Models\Project;

class HrReimbursementWorkspaceService
{
    public function summary(User $user): array
    {
        $claimQuery = HrReimbursementClaim::query()->accessibleTo($user);

        return [
            'open_claims' => (clone $claimQuery)
                ->whereIn('status', [
                    HrReimbursementClaim::STATUS_SUBMITTED,
                    HrReimbursementClaim::STATUS_MANAGER_APPROVED,
                ])
                ->count(),
            'pending_my_approvals' => (clone $claimQuery)
                ->whereIn('status', [
                    HrReimbursementClaim::STATUS_SUBMITTED,
                    HrReimbursementClaim::STATUS_MANAGER_APPROVED,
                ])
                ->where('approver_user_id', $user->id)
                ->count(),
            'approved_30d' => (clone $claimQuery)
                ->whereIn('status', [
                    HrReimbursementClaim::STATUS_FINANCE_APPROVED,
                    HrReimbursementClaim::STATUS_POSTED,
                    HrReimbursementClaim::STATUS_PAID,
                ])
                ->where('approved_at', '>=', now()->subDays(30))
                ->count(),
            'posted_unpaid' => (clone $claimQuery)
                ->where('status', HrReimbursementClaim::STATUS_POSTED)
                ->count(),
            'paid_30d_amount' => round((float) (clone $claimQuery)
                ->where('status', HrReimbursementClaim::STATUS_PAID)
                ->where('updated_at', '>=', now()->subDays(30))
                ->sum('total_amount'), 2),
            'categories' => HrReimbursementCategory::query()->count(),
        ];
    }

    public function employeeOptions(User $user): array
    {
        return HrEmployee::query()
            ->with('user:id,name')
            ->accessibleTo($user)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'employee_number', 'user_id'])
            ->map(fn (HrEmployee $employee) => [
                'id' => $employee->id,
                'name' => $employee->display_name,
                'employee_number' => $employee->employee_number,
                'linked_user_name' => $employee->user?->name,
            ])
            ->values()
            ->all();
    }

    public function categoryOptions(): array
    {
        return HrReimbursementCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'requires_receipt', 'is_project_rebillable'])
            ->map(fn (HrReimbursementCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'requires_receipt' => (bool) $category->requires_receipt,
                'is_project_rebillable' => (bool) $category->is_project_rebillable,
            ])
            ->values()
            ->all();
    }

    public function currencyOptions(string $companyId): array
    {
        return Currency::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol'])
            ->map(fn (Currency $currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
            ])
            ->values()
            ->all();
    }

    public function projectOptions(User $user): array
    {
        if (! $user->hasPermission('projects.projects.view')) {
            return [];
        }

        return Project::query()
            ->accessibleTo($user)
            ->orderBy('project_code')
            ->get(['id', 'project_code', 'name'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
            ])
            ->values()
            ->all();
    }
}
