<?php

namespace App\Core\Inventory\Models;

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

class InventoryLocation extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use CompanyScoped;
    use Auditable;

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_VENDOR = 'vendor';

    public const TYPE_TRANSIT = 'transit';

    /**
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_INTERNAL,
        self::TYPE_CUSTOMER,
        self::TYPE_VENDOR,
        self::TYPE_TRANSIT,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'code',
        'name',
        'type',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function sourceMoves(): HasMany
    {
        return $this->hasMany(InventoryStockMove::class, 'source_location_id');
    }

    public function destinationMoves(): HasMany
    {
        return $this->hasMany(InventoryStockMove::class, 'destination_location_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(InventoryStockLevel::class, 'location_id');
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
