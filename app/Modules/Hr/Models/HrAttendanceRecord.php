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

class HrAttendanceRecord extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_HALF_DAY = 'half_day';

    public const STATUS_MISSING = 'missing';

    public const STATUS_HOLIDAY = 'holiday';

    public const APPROVAL_NOT_REQUIRED = 'not_required';

    public const APPROVAL_SUBMITTED = 'submitted';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_ON_LEAVE,
        self::STATUS_HALF_DAY,
        self::STATUS_MISSING,
        self::STATUS_HOLIDAY,
    ];

    public const APPROVAL_STATUSES = [
        self::APPROVAL_NOT_REQUIRED,
        self::APPROVAL_SUBMITTED,
        self::APPROVAL_APPROVED,
        self::APPROVAL_REJECTED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_id',
        'shift_id',
        'attendance_date',
        'status',
        'check_in_at',
        'check_out_at',
        'worked_minutes',
        'overtime_minutes',
        'late_minutes',
        'approval_status',
        'approved_by_user_id',
        'source_summary',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'worked_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'late_minutes' => 'integer',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HrShift::class, 'shift_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
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

        return $query->whereHas('employee', function (Builder $employeeQuery) use ($user): void {
            $employeeQuery
                ->where('user_id', $user->id)
                ->orWhere('attendance_approver_user_id', $user->id)
                ->orWhereExists(function ($managerQuery) use ($user): void {
                    $managerQuery
                        ->selectRaw('1')
                        ->from('hr_employees as manager_employees')
                        ->whereColumn('manager_employees.id', 'hr_employees.manager_employee_id')
                        ->where('manager_employees.user_id', $user->id)
                        ->whereNull('manager_employees.deleted_at');
                });
        });
    }
}
