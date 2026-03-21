<?php

namespace App\Modules\Projects\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectBillable extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const TYPE_TIMESHEET = 'timesheet';

    public const TYPE_MILESTONE = 'milestone';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_MATERIAL = 'material';

    public const TYPE_MANUAL = 'manual';

    /**
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_TIMESHEET,
        self::TYPE_MILESTONE,
        self::TYPE_EXPENSE,
        self::TYPE_MATERIAL,
        self::TYPE_MANUAL,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_READY,
        self::STATUS_APPROVED,
        self::STATUS_INVOICED,
        self::STATUS_CANCELLED,
    ];

    public const APPROVAL_STATUS_NOT_REQUIRED = 'not_required';

    public const APPROVAL_STATUS_PENDING = 'pending';

    public const APPROVAL_STATUS_APPROVED = 'approved';

    public const APPROVAL_STATUS_REJECTED = 'rejected';

    /**
     * @var array<int, string>
     */
    public const APPROVAL_STATUSES = [
        self::APPROVAL_STATUS_NOT_REQUIRED,
        self::APPROVAL_STATUS_PENDING,
        self::APPROVAL_STATUS_APPROVED,
        self::APPROVAL_STATUS_REJECTED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'project_id',
        'billable_type',
        'source_type',
        'source_id',
        'customer_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'currency_id',
        'status',
        'approval_status',
        'invoice_id',
        'invoice_line_reference',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoice::class, 'invoice_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
