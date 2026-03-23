<?php

namespace App\Modules\Inventory\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Product;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCycleCountLine extends Model
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
        'cycle_count_id',
        'location_id',
        'product_id',
        'lot_id',
        'adjustment_move_id',
        'tracking_mode',
        'lot_code',
        'expected_quantity',
        'counted_quantity',
        'variance_quantity',
        'estimated_unit_cost',
        'variance_value',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'variance_quantity' => 'decimal:4',
            'estimated_unit_cost' => 'decimal:2',
            'variance_value' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cycleCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCycleCount::class, 'cycle_count_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'lot_id');
    }

    public function adjustmentMove(): BelongsTo
    {
        return $this->belongsTo(InventoryStockMove::class, 'adjustment_move_id');
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
