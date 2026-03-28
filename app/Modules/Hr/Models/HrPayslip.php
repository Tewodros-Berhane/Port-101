<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Currency;
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

class HrPayslip extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_POSTED = 'posted';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_POSTED,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_id',
        'payroll_run_id',
        'payroll_period_id',
        'compensation_assignment_id',
        'currency_id',
        'published_by_user_id',
        'payslip_number',
        'status',
        'gross_pay',
        'total_deductions',
        'reimbursement_amount',
        'net_pay',
        'issued_at',
        'paid_at',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'reimbursement_amount' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'published_at' => 'datetime',
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

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(HrPayrollRun::class, 'payroll_run_id');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id');
    }

    public function compensationAssignment(): BelongsTo
    {
        return $this->belongsTo(HrCompensationAssignment::class, 'compensation_assignment_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HrPayslipLine::class, 'payslip_id')
            ->orderBy('line_order')
            ->orderBy('created_at');
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        if ($user->hasPermission('hr.payroll.view')) {
            $scope = $user->dataScopeForCompany();

            if (in_array($scope, [User::DATA_SCOPE_COMPANY, User::DATA_SCOPE_READ_ALL], true)) {
                return $query;
            }
        }

        return $query->whereHas('employee', function (Builder $employeeQuery) use ($user): void {
            $employeeQuery->where('user_id', $user->id);
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
