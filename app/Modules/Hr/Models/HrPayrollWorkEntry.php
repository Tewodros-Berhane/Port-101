<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPayrollWorkEntry extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const TYPE_WORKED_TIME = 'worked_time';

    public const TYPE_LEAVE_PAID = 'leave_paid';

    public const TYPE_LEAVE_UNPAID = 'leave_unpaid';

    public const TYPE_OVERTIME = 'overtime';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_REIMBURSEMENT = 'reimbursement';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CONFLICT = 'conflict';

    public const TYPES = [
        self::TYPE_WORKED_TIME,
        self::TYPE_LEAVE_PAID,
        self::TYPE_LEAVE_UNPAID,
        self::TYPE_OVERTIME,
        self::TYPE_ADJUSTMENT,
        self::TYPE_REIMBURSEMENT,
    ];

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_CONFLICT,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_id',
        'payroll_period_id',
        'payroll_run_id',
        'entry_type',
        'source_type',
        'source_id',
        'from_datetime',
        'to_datetime',
        'quantity',
        'amount_reference',
        'status',
        'conflict_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'from_datetime' => 'datetime',
            'to_datetime' => 'datetime',
            'quantity' => 'decimal:2',
            'amount_reference' => 'decimal:2',
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

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(HrPayrollRun::class, 'payroll_run_id');
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
