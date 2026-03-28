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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrDepartment extends Model
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
        'name',
        'code',
        'manager_employee_id',
        'leave_approver_user_id',
        'attendance_approver_user_id',
        'reimbursement_approver_user_id',
        'payroll_cost_center_reference',
        'created_by',
        'updated_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function managerEmployee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'manager_employee_id');
    }

    public function leaveApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leave_approver_user_id');
    }

    public function attendanceApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendance_approver_user_id');
    }

    public function reimbursementApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reimbursement_approver_user_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'department_id');
    }
}
