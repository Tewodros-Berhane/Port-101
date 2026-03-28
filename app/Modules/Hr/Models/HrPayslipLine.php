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

class HrPayslipLine extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    public const TYPE_REIMBURSEMENT = 'reimbursement';

    public const TYPE_EMPLOYER_COST = 'employer_cost';

    public const TYPES = [
        self::TYPE_EARNING,
        self::TYPE_DEDUCTION,
        self::TYPE_REIMBURSEMENT,
        self::TYPE_EMPLOYER_COST,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'payslip_id',
        'line_type',
        'code',
        'name',
        'line_order',
        'quantity',
        'rate',
        'amount',
        'source_type',
        'source_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'line_order' => 'integer',
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(HrPayslip::class, 'payslip_id');
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
