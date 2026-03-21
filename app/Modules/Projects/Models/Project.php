<?php

namespace App\Modules\Projects\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const BILLING_TYPE_FIXED_FEE = 'fixed_fee';

    public const BILLING_TYPE_TIME_AND_MATERIAL = 'time_and_material';

    public const BILLING_TYPE_NON_BILLABLE = 'non_billable';

    public const BILLING_TYPE_MIXED = 'mixed';

    /**
     * @var array<int, string>
     */
    public const BILLING_TYPES = [
        self::BILLING_TYPE_FIXED_FEE,
        self::BILLING_TYPE_TIME_AND_MATERIAL,
        self::BILLING_TYPE_NON_BILLABLE,
        self::BILLING_TYPE_MIXED,
    ];

    public const HEALTH_STATUS_ON_TRACK = 'on_track';

    public const HEALTH_STATUS_AT_RISK = 'at_risk';

    public const HEALTH_STATUS_OFF_TRACK = 'off_track';

    /**
     * @var array<int, string>
     */
    public const HEALTH_STATUSES = [
        self::HEALTH_STATUS_ON_TRACK,
        self::HEALTH_STATUS_AT_RISK,
        self::HEALTH_STATUS_OFF_TRACK,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'customer_id',
        'sales_order_id',
        'currency_id',
        'project_code',
        'name',
        'description',
        'status',
        'billing_type',
        'project_manager_id',
        'start_date',
        'target_end_date',
        'completed_at',
        'budget_amount',
        'budget_hours',
        'actual_cost_amount',
        'actual_billable_amount',
        'progress_percent',
        'health_status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_end_date' => 'date',
            'completed_at' => 'datetime',
            'budget_amount' => 'decimal:2',
            'budget_hours' => 'decimal:2',
            'actual_cost_amount' => 'decimal:2',
            'actual_billable_amount' => 'decimal:2',
            'progress_percent' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(ProjectTimesheet::class, 'project_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'project_id')
            ->orderBy('sequence');
    }

    public function billables(): HasMany
    {
        return $this->hasMany(ProjectBillable::class, 'project_id');
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
