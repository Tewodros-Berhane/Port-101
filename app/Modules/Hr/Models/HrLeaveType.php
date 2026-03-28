<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrLeaveType extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const UNIT_DAYS = 'days';

    public const UNIT_HOURS = 'hours';

    public const UNITS = [
        self::UNIT_DAYS,
        self::UNIT_HOURS,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'unit',
        'requires_allocation',
        'is_paid',
        'requires_approval',
        'allow_negative_balance',
        'max_consecutive_days',
        'color',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requires_allocation' => 'boolean',
            'is_paid' => 'boolean',
            'requires_approval' => 'boolean',
            'allow_negative_balance' => 'boolean',
            'max_consecutive_days' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(HrLeaveAllocation::class, 'leave_type_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(HrLeaveRequest::class, 'leave_type_id');
    }
}
