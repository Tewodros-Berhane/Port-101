<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrLeaveAllocation extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'leave_period_id',
        'allocated_amount',
        'used_amount',
        'balance_amount',
        'carry_forward_amount',
        'expires_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'used_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'carry_forward_amount' => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrLeaveType::class, 'leave_type_id');
    }

    public function leavePeriod(): BelongsTo
    {
        return $this->belongsTo(HrLeavePeriod::class, 'leave_period_id');
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        $scope = $user->dataScopeForCompany();

        if (in_array($scope, [User::DATA_SCOPE_COMPANY, User::DATA_SCOPE_READ_ALL], true)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->whereHas('employee', function (Builder $employeeQuery) use ($user): void {
                $employeeQuery
                    ->where('user_id', $user->id)
                    ->orWhere('leave_approver_user_id', $user->id)
                    ->orWhereExists(function ($managerQuery) use ($user): void {
                        $managerQuery
                            ->selectRaw('1')
                            ->from('hr_employees as manager_employees')
                            ->whereColumn('manager_employees.id', 'hr_employees.manager_employee_id')
                            ->where('manager_employees.user_id', $user->id)
                            ->whereNull('manager_employees.deleted_at');
                    });
            });
        });
    }
}
