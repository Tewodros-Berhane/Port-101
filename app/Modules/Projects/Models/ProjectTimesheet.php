<?php

namespace App\Modules\Projects\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTimesheet extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const APPROVAL_STATUS_DRAFT = 'draft';

    public const APPROVAL_STATUS_SUBMITTED = 'submitted';

    public const APPROVAL_STATUS_APPROVED = 'approved';

    public const APPROVAL_STATUS_REJECTED = 'rejected';

    /**
     * @var array<int, string>
     */
    public const APPROVAL_STATUSES = [
        self::APPROVAL_STATUS_DRAFT,
        self::APPROVAL_STATUS_SUBMITTED,
        self::APPROVAL_STATUS_APPROVED,
        self::APPROVAL_STATUS_REJECTED,
    ];

    public const INVOICE_STATUS_NOT_READY = 'not_ready';

    public const INVOICE_STATUS_READY = 'ready';

    public const INVOICE_STATUS_INVOICED = 'invoiced';

    public const INVOICE_STATUS_NON_BILLABLE = 'non_billable';

    /**
     * @var array<int, string>
     */
    public const INVOICE_STATUSES = [
        self::INVOICE_STATUS_NOT_READY,
        self::INVOICE_STATUS_READY,
        self::INVOICE_STATUS_INVOICED,
        self::INVOICE_STATUS_NON_BILLABLE,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'project_id',
        'task_id',
        'user_id',
        'work_date',
        'description',
        'hours',
        'is_billable',
        'cost_rate',
        'bill_rate',
        'cost_amount',
        'billable_amount',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'invoice_status',
        'source_type',
        'source_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'decimal:2',
            'is_billable' => 'boolean',
            'cost_rate' => 'decimal:2',
            'bill_rate' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'billable_amount' => 'decimal:2',
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

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function billables(): MorphMany
    {
        return $this->morphMany(ProjectBillable::class, 'source');
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
