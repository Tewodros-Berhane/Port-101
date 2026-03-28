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

class HrAttendanceCheckin extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_WEB = 'web';

    public const SOURCE_MOBILE = 'mobile';

    public const SOURCE_BIOMETRIC = 'biometric';

    public const SOURCE_IMPORT = 'import';

    public const LOG_TYPES = [
        self::TYPE_IN,
        self::TYPE_OUT,
    ];

    public const SOURCES = [
        self::SOURCE_MANUAL,
        self::SOURCE_WEB,
        self::SOURCE_MOBILE,
        self::SOURCE_BIOMETRIC,
        self::SOURCE_IMPORT,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_id',
        'recorded_at',
        'log_type',
        'source',
        'location_data',
        'device_reference',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'location_data' => 'array',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
