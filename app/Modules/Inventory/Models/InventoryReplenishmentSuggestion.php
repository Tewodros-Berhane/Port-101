<?php

namespace App\Modules\Inventory\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use App\Modules\Purchasing\Models\PurchaseRfq;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryReplenishmentSuggestion extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CONVERTED,
        self::STATUS_DISMISSED,
        self::STATUS_RESOLVED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'reorder_rule_id',
        'product_id',
        'location_id',
        'preferred_vendor_id',
        'rfq_id',
        'status',
        'on_hand_quantity',
        'reserved_quantity',
        'available_quantity',
        'inbound_quantity',
        'projected_quantity',
        'min_quantity',
        'max_quantity',
        'suggested_quantity',
        'triggered_at',
        'converted_at',
        'dismissed_at',
        'resolved_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'available_quantity' => 'decimal:4',
            'inbound_quantity' => 'decimal:4',
            'projected_quantity' => 'decimal:4',
            'min_quantity' => 'decimal:4',
            'max_quantity' => 'decimal:4',
            'suggested_quantity' => 'decimal:4',
            'triggered_at' => 'datetime',
            'converted_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reorderRule(): BelongsTo
    {
        return $this->belongsTo(InventoryReorderRule::class, 'reorder_rule_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'preferred_vendor_id');
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'rfq_id');
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
