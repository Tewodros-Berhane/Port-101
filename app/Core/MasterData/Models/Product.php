<?php

namespace App\Core\MasterData\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    public const TYPE_STOCK = 'stock';

    public const TYPE_SERVICE = 'service';

    public const TRACKING_NONE = 'none';

    public const TRACKING_LOT = 'lot';

    public const TRACKING_SERIAL = 'serial';

    /**
     * @var array<int, string>
     */
    public const TRACKING_MODES = [
        self::TRACKING_NONE,
        self::TRACKING_LOT,
        self::TRACKING_SERIAL,
    ];

    protected $fillable = [
        'company_id',
        'sku',
        'name',
        'type',
        'tracking_mode',
        'uom_id',
        'default_tax_id',
        'description',
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

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function defaultTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'default_tax_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function usesInventoryTracking(): bool
    {
        return $this->type === self::TYPE_STOCK
            && in_array($this->tracking_mode, [self::TRACKING_LOT, self::TRACKING_SERIAL], true);
    }
}
