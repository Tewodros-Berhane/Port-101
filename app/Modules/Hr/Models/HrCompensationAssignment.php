<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Currency;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrCompensationAssignment extends Model
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
        'employee_id',
        'contract_id',
        'salary_structure_id',
        'currency_id',
        'effective_from',
        'effective_to',
        'pay_frequency',
        'salary_basis',
        'base_salary_amount',
        'hourly_rate',
        'payroll_group',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'base_salary_amount' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(HrEmployeeContract::class, 'contract_id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(HrSalaryStructure::class, 'salary_structure_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(HrPayslip::class, 'compensation_assignment_id');
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
