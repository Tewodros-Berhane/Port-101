<?php

namespace App\Modules\Projects\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectMember extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const ROLE_MANAGER = 'manager';

    public const ROLE_MEMBER = 'member';

    public const ROLE_REVIEWER = 'reviewer';

    public const ROLE_BILLING_OWNER = 'billing_owner';

    /**
     * @var array<int, string>
     */
    public const ROLES = [
        self::ROLE_MANAGER,
        self::ROLE_MEMBER,
        self::ROLE_REVIEWER,
        self::ROLE_BILLING_OWNER,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'project_id',
        'user_id',
        'project_role',
        'allocation_percent',
        'hourly_cost_rate',
        'hourly_bill_rate',
        'is_billable_by_default',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allocation_percent' => 'decimal:2',
            'hourly_cost_rate' => 'decimal:2',
            'hourly_bill_rate' => 'decimal:2',
            'is_billable_by_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
