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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployee extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_OFFBOARDED = 'offboarded';

    public const TYPE_FULL_TIME = 'full_time';

    public const TYPE_PART_TIME = 'part_time';

    public const TYPE_CONTRACT = 'contract';

    public const TYPE_INTERN = 'intern';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_LEAVE,
        self::STATUS_INACTIVE,
        self::STATUS_OFFBOARDED,
    ];

    public const TYPES = [
        self::TYPE_FULL_TIME,
        self::TYPE_PART_TIME,
        self::TYPE_CONTRACT,
        self::TYPE_INTERN,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'department_id',
        'designation_id',
        'employee_number',
        'employment_status',
        'employment_type',
        'first_name',
        'last_name',
        'display_name',
        'work_email',
        'personal_email',
        'work_phone',
        'personal_phone',
        'date_of_birth',
        'hire_date',
        'termination_date',
        'manager_employee_id',
        'attendance_approver_user_id',
        'leave_approver_user_id',
        'reimbursement_approver_user_id',
        'timezone',
        'country_code',
        'work_location',
        'bank_account_reference',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(HrDesignation::class, 'designation_id');
    }

    public function managerEmployee(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_employee_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_employee_id');
    }

    public function attendanceApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_approver_user_id');
    }

    public function leaveApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leave_approver_user_id');
    }

    public function reimbursementApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reimbursement_approver_user_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(HrEmployeeContract::class, 'employee_id')
            ->orderByDesc('start_date')
            ->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(HrEmployeeDocument::class, 'employee_id')
            ->orderByDesc('created_at');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(HrShiftAssignment::class, 'employee_id')
            ->orderByDesc('from_date');
    }

    public function attendanceCheckins(): HasMany
    {
        return $this->hasMany(HrAttendanceCheckin::class, 'employee_id')
            ->orderByDesc('recorded_at');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(HrAttendanceRecord::class, 'employee_id')
            ->orderByDesc('attendance_date');
    }

    public function attendanceRequests(): HasMany
    {
        return $this->hasMany(HrAttendanceRequest::class, 'employee_id')
            ->orderByDesc('created_at');
    }

    public function reimbursementClaims(): HasMany
    {
        return $this->hasMany(HrReimbursementClaim::class, 'employee_id')
            ->orderByDesc('created_at');
    }

    public function compensationAssignments(): HasMany
    {
        return $this->hasMany(HrCompensationAssignment::class, 'employee_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at');
    }

    public function payrollWorkEntries(): HasMany
    {
        return $this->hasMany(HrPayrollWorkEntry::class, 'employee_id')
            ->orderByDesc('from_datetime')
            ->orderByDesc('created_at');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(HrPayslip::class, 'employee_id')
            ->orderByDesc('created_at');
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

        $query->where(function (Builder $builder) use ($user, $scope): void {
            $builder
                ->where('hr_employees.user_id', $user->id)
                ->orWhere('hr_employees.created_by', $user->id)
                ->orWhere('hr_employees.attendance_approver_user_id', $user->id)
                ->orWhere('hr_employees.leave_approver_user_id', $user->id)
                ->orWhere('hr_employees.reimbursement_approver_user_id', $user->id)
                ->orWhereExists(function ($employeeQuery) use ($user): void {
                    $employeeQuery
                        ->selectRaw('1')
                        ->from('hr_employees as manager_employees')
                        ->whereColumn('manager_employees.id', 'hr_employees.manager_employee_id')
                        ->where('manager_employees.user_id', $user->id)
                        ->whereNull('manager_employees.deleted_at');
                });

            if ($scope !== User::DATA_SCOPE_TEAM) {
                return;
            }
        });

        return $query;
    }
}
