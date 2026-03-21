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
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectMilestone extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_READY_FOR_REVIEW = 'ready_for_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_BILLED = 'billed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_READY_FOR_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_BILLED,
        self::STATUS_CANCELLED,
    ];

    public const INVOICE_STATUS_NOT_READY = 'not_ready';

    public const INVOICE_STATUS_READY = 'ready';

    public const INVOICE_STATUS_INVOICED = 'invoiced';

    /**
     * @var array<int, string>
     */
    public const INVOICE_STATUSES = [
        self::INVOICE_STATUS_NOT_READY,
        self::INVOICE_STATUS_READY,
        self::INVOICE_STATUS_INVOICED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'project_id',
        'name',
        'description',
        'sequence',
        'status',
        'due_date',
        'completed_at',
        'approved_by',
        'approved_at',
        'amount',
        'invoice_status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
            'amount' => 'decimal:2',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
