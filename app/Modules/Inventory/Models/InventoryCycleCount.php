<?php

namespace App\Modules\Inventory\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCycleCount extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const APPROVAL_STATUS_NOT_REQUIRED = 'not_required';

    public const APPROVAL_STATUS_PENDING = 'pending';

    public const APPROVAL_STATUS_APPROVED = 'approved';

    public const APPROVAL_STATUS_REJECTED = 'rejected';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'reference',
        'warehouse_id',
        'location_id',
        'status',
        'line_count',
        'total_expected_quantity',
        'total_counted_quantity',
        'total_variance_quantity',
        'total_absolute_variance_quantity',
        'total_variance_value',
        'total_absolute_variance_value',
        'requires_approval',
        'approval_status',
        'started_at',
        'started_by',
        'reviewed_at',
        'reviewed_by',
        'posted_at',
        'posted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'line_count' => 'integer',
            'total_expected_quantity' => 'decimal:4',
            'total_counted_quantity' => 'decimal:4',
            'total_variance_quantity' => 'decimal:4',
            'total_absolute_variance_quantity' => 'decimal:4',
            'total_variance_value' => 'decimal:2',
            'total_absolute_variance_value' => 'decimal:2',
            'requires_approval' => 'boolean',
            'started_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'posted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCycleCountLine::class, 'cycle_count_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function adjustmentMoves(): HasMany
    {
        return $this->hasMany(InventoryStockMove::class, 'cycle_count_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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
