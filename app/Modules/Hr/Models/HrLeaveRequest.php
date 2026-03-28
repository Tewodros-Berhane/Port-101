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

class HrLeaveRequest extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYROLL_STATUS_OPEN = 'open';

    public const PAYROLL_STATUS_CONSUMED = 'consumed';

    public const PAYROLL_STATUS_DEFERRED = 'deferred';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const PAYROLL_STATUSES = [
        self::PAYROLL_STATUS_OPEN,
        self::PAYROLL_STATUS_CONSUMED,
        self::PAYROLL_STATUS_DEFERRED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'leave_period_id',
        'requested_by_user_id',
        'approver_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'cancelled_by_user_id',
        'request_number',
        'status',
        'from_date',
        'to_date',
        'duration_amount',
        'is_half_day',
        'reason',
        'decision_notes',
        'payroll_status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'duration_amount' => 'decimal:2',
            'is_half_day' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
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
            $builder
                ->where('requested_by_user_id', $user->id)
                ->orWhere('approver_user_id', $user->id)
                ->orWhereHas('employee', function (Builder $employeeQuery) use ($user): void {
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
