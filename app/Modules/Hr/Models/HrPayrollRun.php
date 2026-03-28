<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingManualJournal;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrPayrollRun extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PREPARED = 'prepared';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PREPARED,
        self::STATUS_APPROVED,
        self::STATUS_POSTED,
        self::STATUS_CANCELLED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'payroll_period_id',
        'approver_user_id',
        'prepared_by_user_id',
        'approved_by_user_id',
        'posted_by_user_id',
        'accounting_manual_journal_id',
        'run_number',
        'status',
        'total_gross',
        'total_deductions',
        'total_reimbursements',
        'total_net',
        'prepared_at',
        'approved_at',
        'posted_at',
        'cancelled_at',
        'decision_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_reimbursements' => 'decimal:2',
            'total_net' => 'decimal:2',
            'prepared_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(HrPayrollPeriod::class, 'payroll_period_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function accountingManualJournal(): BelongsTo
    {
        return $this->belongsTo(AccountingManualJournal::class, 'accounting_manual_journal_id');
    }

    public function workEntries(): HasMany
    {
        return $this->hasMany(HrPayrollWorkEntry::class, 'payroll_run_id')
            ->orderBy('employee_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(HrPayslip::class, 'payroll_run_id')
            ->orderBy('payslip_number');
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
