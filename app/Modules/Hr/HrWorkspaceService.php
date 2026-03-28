<?php

namespace App\Modules\Hr;

use App\Core\Company\Models\CompanyUser;
use App\Models\User;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Hr\Models\HrDesignation;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrEmployeeDocument;

class HrWorkspaceService
{
    public function summary(User $user): array
    {
        $employeeQuery = HrEmployee::query()->accessibleTo($user);
        $contractQuery = HrEmployeeContract::query();
        $documentQuery = HrEmployeeDocument::query();

        $user->applyDataScopeToQuery($contractQuery);
        $user->applyDataScopeToQuery($documentQuery);

        return [
            'employees' => (clone $employeeQuery)->count(),
            'active_employees' => (clone $employeeQuery)
                ->where('employment_status', HrEmployee::STATUS_ACTIVE)
                ->count(),
            'draft_employees' => (clone $employeeQuery)
                ->where('employment_status', HrEmployee::STATUS_DRAFT)
                ->count(),
            'active_contracts' => (clone $contractQuery)
                ->where('status', HrEmployeeContract::STATUS_ACTIVE)
                ->count(),
            'documents' => (clone $documentQuery)->count(),
            'documents_expiring_30d' => (clone $documentQuery)
                ->whereNotNull('valid_until')
                ->whereBetween('valid_until', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count(),
        ];
    }

    public function departmentOptions(): array
    {
        return HrDepartment::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (HrDepartment $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
            ])
            ->values()
            ->all();
    }

    public function designationOptions(): array
    {
        return HrDesignation::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (HrDesignation $designation) => [
                'id' => $designation->id,
                'name' => $designation->name,
                'code' => $designation->code,
            ])
            ->values()
            ->all();
    }

    public function managerOptions(): array
    {
        return HrEmployee::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'employee_number'])
            ->map(fn (HrEmployee $employee) => [
                'id' => $employee->id,
                'name' => $employee->display_name,
                'employee_number' => $employee->employee_number,
            ])
            ->values()
            ->all();
    }

    public function companyUserOptions(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->with('user:id,name,email')
            ->get()
            ->filter(fn (CompanyUser $membership) => $membership->user !== null)
            ->map(fn (CompanyUser $membership) => [
                'id' => (string) $membership->user_id,
                'name' => (string) $membership->user?->name,
                'email' => (string) $membership->user?->email,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }
}
