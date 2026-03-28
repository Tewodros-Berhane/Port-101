<?php

namespace App\Modules\Approvals\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalRequest extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const MODULE_SALES = 'sales';

    public const MODULE_PURCHASING = 'purchasing';

    public const MODULE_INVENTORY = 'inventory';

    public const MODULE_ACCOUNTING = 'accounting';

    public const MODULE_PROJECTS = 'projects';

    public const MODULE_HR = 'hr';

    /**
     * @var array<int, string>
     */
    public const MODULES = [
        self::MODULE_SALES,
        self::MODULE_PURCHASING,
        self::MODULE_INVENTORY,
        self::MODULE_ACCOUNTING,
        self::MODULE_PROJECTS,
        self::MODULE_HR,
    ];

    public const ACTION_SALES_QUOTE_APPROVAL = 'sales_quote_approval';

    public const ACTION_SALES_ORDER_APPROVAL = 'sales_order_approval';

    public const ACTION_PURCHASE_ORDER_APPROVAL = 'po_final_approval';

    public const ACTION_ACCOUNTING_MANUAL_JOURNAL_APPROVAL = 'manual_journal_approval';

    public const ACTION_PROJECT_BILLABLE_APPROVAL = 'project_billable_approval';

    public const ACTION_INVENTORY_CYCLE_COUNT_APPROVAL = 'inventory_cycle_count_approval';

    public const ACTION_HR_LEAVE_APPROVAL = 'hr_leave_approval';

    public const ACTION_HR_ATTENDANCE_APPROVAL = 'hr_attendance_approval';

    public const ACTION_HR_REIMBURSEMENT_APPROVAL = 'hr_reimbursement_approval';

    public const ACTION_HR_PAYROLL_APPROVAL = 'hr_payroll_approval';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const RISK_CRITICAL = 'critical';

    /**
     * @var array<int, string>
     */
    public const RISK_LEVELS = [
        self::RISK_LOW,
        self::RISK_MEDIUM,
        self::RISK_HIGH,
        self::RISK_CRITICAL,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'module',
        'action',
        'source_type',
        'source_id',
        'source_number',
        'status',
        'requested_by_user_id',
        'requested_at',
        'amount',
        'currency_code',
        'risk_level',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'approval_request_id')
            ->orderBy('step_order');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
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
