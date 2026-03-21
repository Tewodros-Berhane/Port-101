<?php

namespace App\Modules\Projects\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTask extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_TODO = 'todo';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_REVIEW = 'review';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_BLOCKED,
        self::STATUS_REVIEW,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    /**
     * @var array<int, string>
     */
    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    public const BILLING_STATUS_NOT_READY = 'not_ready';

    public const BILLING_STATUS_READY = 'ready';

    public const BILLING_STATUS_BILLED = 'billed';

    public const BILLING_STATUS_NON_BILLABLE = 'non_billable';

    /**
     * @var array<int, string>
     */
    public const BILLING_STATUSES = [
        self::BILLING_STATUS_NOT_READY,
        self::BILLING_STATUS_READY,
        self::BILLING_STATUS_BILLED,
        self::BILLING_STATUS_NON_BILLABLE,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'project_id',
        'stage_id',
        'parent_task_id',
        'customer_id',
        'task_number',
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
        'start_date',
        'due_date',
        'completed_at',
        'estimated_hours',
        'actual_hours',
        'is_billable',
        'billing_status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
            'is_billable' => 'boolean',
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

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function childTasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(ProjectTimesheet::class, 'task_id');
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
