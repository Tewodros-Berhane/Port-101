<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Currency;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrEmployeeContract extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TERMINATED = 'terminated';

    public const PAY_FREQUENCY_WEEKLY = 'weekly';

    public const PAY_FREQUENCY_BIWEEKLY = 'biweekly';

    public const PAY_FREQUENCY_MONTHLY = 'monthly';

    public const SALARY_BASIS_FIXED = 'fixed';

    public const SALARY_BASIS_HOURLY = 'hourly';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_TERMINATED,
    ];

    public const PAY_FREQUENCIES = [
        self::PAY_FREQUENCY_WEEKLY,
        self::PAY_FREQUENCY_BIWEEKLY,
        self::PAY_FREQUENCY_MONTHLY,
    ];

    public const SALARY_BASES = [
        self::SALARY_BASIS_FIXED,
        self::SALARY_BASIS_HOURLY,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_id',
        'contract_number',
        'status',
        'start_date',
        'end_date',
        'pay_frequency',
        'salary_basis',
        'base_salary_amount',
        'hourly_rate',
        'currency_id',
        'working_days_per_week',
        'standard_hours_per_day',
        'is_payroll_eligible',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'base_salary_amount' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'standard_hours_per_day' => 'decimal:2',
            'is_payroll_eligible' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
