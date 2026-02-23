<?php

namespace App\Modules\Inventory\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Product;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockLevel extends Model
{
    use HasFactory;
    use HasUuids;
    use CompanyScoped;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'location_id',
        'product_id',
        'on_hand_quantity',
        'reserved_quantity',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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


