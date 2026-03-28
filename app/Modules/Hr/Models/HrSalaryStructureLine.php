<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrSalaryStructureLine extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPES = [
        self::TYPE_EARNING,
        self::TYPE_DEDUCTION,
    ];

    public const CALCULATION_FIXED = 'fixed';

    public const CALCULATION_PERCENTAGE = 'percentage';

    public const CALCULATION_TYPES = [
        self::CALCULATION_FIXED,
        self::CALCULATION_PERCENTAGE,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'salary_structure_id',
        'line_type',
        'calculation_type',
        'code',
        'name',
        'line_order',
        'amount',
        'percentage_rate',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'line_order' => 'integer',
            'amount' => 'decimal:2',
            'percentage_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(HrSalaryStructure::class, 'salary_structure_id');
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
