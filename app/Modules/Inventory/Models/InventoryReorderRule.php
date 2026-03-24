<?php

namespace App\Modules\Inventory\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryReorderRule extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'product_id',
        'location_id',
        'preferred_vendor_id',
        'min_quantity',
        'max_quantity',
        'reorder_quantity',
        'lead_time_days',
        'is_active',
        'last_evaluated_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'decimal:4',
            'max_quantity' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'lead_time_days' => 'integer',
            'is_active' => 'boolean',
            'last_evaluated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function suggestions(): HasMany
    {
        return $this->hasMany(InventoryReplenishmentSuggestion::class, 'reorder_rule_id')
            ->latest('triggered_at')
            ->latest('created_at');
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
