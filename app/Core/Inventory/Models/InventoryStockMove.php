<?php

namespace App\Core\Inventory\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Product;
use App\Core\Sales\Models\SalesOrder;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryStockMove extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use CompanyScoped;
    use Auditable;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_DELIVERY = 'delivery';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_RECEIPT,
        self::TYPE_DELIVERY,
        self::TYPE_TRANSFER,
        self::TYPE_ADJUSTMENT,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_DONE = 'done';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RESERVED,
        self::STATUS_DONE,
        self::STATUS_CANCELLED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'reference',
        'move_type',
        'status',
        'source_location_id',
        'destination_location_id',
        'product_id',
        'quantity',
        'related_sales_order_id',
        'reserved_at',
        'reserved_by',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'reserved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'related_sales_order_id');
    }

    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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
