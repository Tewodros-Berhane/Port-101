<?php

namespace App\Modules\Hr;

use App\Models\User;
use App\Modules\Hr\Models\HrDepartment;
use App\Modules\Hr\Models\HrDesignation;
use App\Modules\Hr\Models\HrEmployee;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HrEmployeeService
{
    public function create(array $attributes, User $actor): HrEmployee
    {
        return DB::transaction(function () use ($attributes, $actor): HrEmployee {
            $companyId = (string) $actor->current_company_id;
            $departmentId = $this->resolveDepartmentId($companyId, $attributes, $actor->id);
            $designationId = $this->resolveDesignationId($companyId, $attributes, $actor->id);
            $employeeNumber = $this->resolveEmployeeNumber($companyId, $attributes['employee_number'] ?? null);
            $displayName = trim((string) $attributes['first_name'].' '.(string) $attributes['last_name']);

            return HrEmployee::create([
                ...Arr::except($attributes, ['department_name', 'designation_name']),
                'company_id' => $companyId,
                'department_id' => $departmentId,
                'designation_id' => $designationId,
                'employee_number' => $employeeNumber,
                'display_name' => $displayName,
                'timezone' => (string) ($attributes['timezone'] ?? $actor->timezone ?? 'UTC'),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function update(HrEmployee $employee, array $attributes, User $actor): HrEmployee
    {
        return DB::transaction(function () use ($employee, $attributes, $actor): HrEmployee {
            $departmentId = $this->resolveDepartmentId((string) $employee->company_id, $attributes, $actor->id);
            $designationId = $this->resolveDesignationId((string) $employee->company_id, $attributes, $actor->id);
            $employeeNumber = $this->resolveEmployeeNumber(
                companyId: (string) $employee->company_id,
                proposed: $attributes['employee_number'] ?? null,
                current: (string) $employee->employee_number,
            );
            $displayName = trim((string) $attributes['first_name'].' '.(string) $attributes['last_name']);

            $employee->update([
                ...Arr::except($attributes, ['department_name', 'designation_name']),
                'department_id' => $departmentId,
                'designation_id' => $designationId,
                'employee_number' => $employeeNumber,
                'display_name' => $displayName,
                'updated_by' => $actor->id,
            ]);

            return $employee->fresh() ?? $employee;
        });
    }

    private function resolveDepartmentId(string $companyId, array $attributes, ?string $actorId): ?string
    {
        if (filled($attributes['department_id'] ?? null)) {
            return (string) $attributes['department_id'];
        }

        $departmentName = trim((string) ($attributes['department_name'] ?? ''));

        if ($departmentName === '') {
            return null;
        }

        $department = HrDepartment::withTrashed()->firstOrCreate(
            [
                'company_id' => $companyId,
                'name' => $departmentName,
            ],
            [
                'code' => Str::upper(Str::limit(Str::slug($departmentName, ''), 12, '')) ?: null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ],
        );

        if ($department->trashed()) {
            $department->restore();
        }

        if (! $department->updated_by && $actorId) {
            $department->forceFill(['updated_by' => $actorId])->save();
        }

        return (string) $department->id;
    }

    private function resolveDesignationId(string $companyId, array $attributes, ?string $actorId): ?string
    {
        if (filled($attributes['designation_id'] ?? null)) {
            return (string) $attributes['designation_id'];
        }

        $designationName = trim((string) ($attributes['designation_name'] ?? ''));

        if ($designationName === '') {
            return null;
        }

        $designation = HrDesignation::withTrashed()->firstOrCreate(
            [
                'company_id' => $companyId,
                'name' => $designationName,
            ],
            [
                'code' => Str::upper(Str::limit(Str::slug($designationName, ''), 12, '')) ?: null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ],
        );

        if ($designation->trashed()) {
            $designation->restore();
        }

        if (! $designation->updated_by && $actorId) {
            $designation->forceFill(['updated_by' => $actorId])->save();
        }

        return (string) $designation->id;
    }

    private function resolveEmployeeNumber(string $companyId, mixed $proposed, ?string $current = null): string
    {
        $candidate = trim((string) $proposed);

        if ($candidate !== '') {
            return $candidate;
        }

        if ($current) {
            return $current;
        }

        $prefix = 'EMP-';
        $latest = HrEmployee::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('employee_number', 'like', $prefix.'%')
            ->orderByDesc('employee_number')
            ->value('employee_number');

        $sequence = $latest ? ((int) Str::afterLast((string) $latest, '-')) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
