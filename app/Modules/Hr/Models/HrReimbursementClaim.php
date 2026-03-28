<?php

namespace App\Modules\Hr\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Currency;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrReimbursementClaim extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_MANAGER_APPROVED = 'manager_approved';

    public const STATUS_FINANCE_APPROVED = 'finance_approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_POSTED = 'posted';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_MANAGER_APPROVED,
        self::STATUS_FINANCE_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_POSTED,
        self::STATUS_PAID,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'employee_id',
        'currency_id',
        'claim_number',
        'status',
        'total_amount',
        'requested_by_user_id',
        'approver_user_id',
        'manager_approver_user_id',
        'finance_approver_user_id',
        'manager_approved_by_user_id',
        'finance_approved_by_user_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'accounting_invoice_id',
        'accounting_payment_id',
        'payslip_id',
        'project_id',
        'notes',
        'decision_notes',
        'submitted_at',
        'manager_approved_at',
        'finance_approved_at',
        'approved_at',
        'rejected_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'manager_approved_at' => 'datetime',
            'finance_approved_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(HrReimbursementClaimLine::class, 'claim_id')
            ->orderBy('expense_date')
            ->orderBy('created_at');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approver_user_id');
    }

    public function financeApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approver_user_id');
    }

    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by_user_id');
    }

    public function financeApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function accountingInvoice(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoice::class, 'accounting_invoice_id');
    }

    public function accountingPayment(): BelongsTo
    {
        return $this->belongsTo(AccountingPayment::class, 'accounting_payment_id');
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        $scope = $user->dataScopeForCompany();

        if (in_array($scope, [User::DATA_SCOPE_COMPANY, User::DATA_SCOPE_READ_ALL], true)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder
                ->where('requested_by_user_id', $user->id)
                ->orWhere('approver_user_id', $user->id)
                ->orWhere('manager_approver_user_id', $user->id)
                ->orWhere('finance_approver_user_id', $user->id)
                ->orWhereHas('employee', function (Builder $employeeQuery) use ($user): void {
                    $employeeQuery
                        ->where('user_id', $user->id)
                        ->orWhere('reimbursement_approver_user_id', $user->id)
                        ->orWhereExists(function ($managerQuery) use ($user): void {
                            $managerQuery
                                ->selectRaw('1')
                                ->from('hr_employees as manager_employees')
                                ->whereColumn('manager_employees.id', 'hr_employees.manager_employee_id')
                                ->where('manager_employees.user_id', $user->id)
                                ->whereNull('manager_employees.deleted_at');
                        });
                });
        });
    }
}
