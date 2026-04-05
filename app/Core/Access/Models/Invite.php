<?php

namespace App\Core\Access\Models;

use App\Core\Company\Models\Company;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invite extends Model
{
    use HasFactory;
    use HasUuids;

    public const DELIVERY_PENDING = 'pending';

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'name',
        'role',
        'company_id',
        'employee_id',
        'company_role_id',
        'token',
        'expires_at',
        'accepted_at',
        'delivery_status',
        'delivery_attempts',
        'last_delivery_at',
        'last_delivery_error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'delivery_attempts' => 'integer',
            'last_delivery_at' => 'datetime',
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

    public function companyRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'company_role_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at');
    }

    public function scopeForTarget(
        Builder $query,
        string $email,
        string $role,
        ?string $companyId,
    ): Builder {
        return $query
            ->whereRaw('LOWER(email) = ?', [self::normalizeEmail($email)])
            ->where('role', $role)
            ->when(
                $companyId !== null && $companyId !== '',
                fn (Builder $builder) => $builder->where('company_id', $companyId),
                fn (Builder $builder) => $builder->whereNull('company_id'),
            );
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
