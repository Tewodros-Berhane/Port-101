<?php

namespace App\Core\Approvals\Models;

use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAuthorityProfile extends Model
{
    use HasFactory;
    use HasUuids;

    public const RISK_LEVELS = [
        'low',
        'medium',
        'high',
        'critical',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'user_id',
        'role_id',
        'module',
        'action',
        'max_amount',
        'currency_code',
        'max_risk_level',
        'requires_separate_requester',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'max_amount' => 'decimal:2',
            'requires_separate_requester' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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
