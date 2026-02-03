<?php

namespace App\Models;

use App\Core\Company\Models\Company;
use App\Core\Company\Models\CompanyUser;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUuids, Notifiable, TwoFactorAuthenticatable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_company_id',
        'locale',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_super_admin' => 'boolean',
        ];
    }

    private function isMasterDataPermission(string $permission): bool
    {
        $prefixes = [
            'core.partners',
            'core.products',
            'core.taxes',
            'core.currencies',
            'core.uoms',
            'core.price_lists',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($permission, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }

    private function isMasterDataManagePermission(string $permission): bool
    {
        return $this->isMasterDataPermission($permission)
            && str_ends_with($permission, '.manage');
    }

    private function isOwnerForCompany(?Company $company = null): bool
    {
        $companyId = $company?->id ?? $this->current_company_id;

        if (! $companyId) {
            return false;
        }

        return $this->memberships()
            ->where('company_id', $companyId)
            ->where('is_owner', true)
            ->exists();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
            ->withPivot(['role_id', 'is_owner'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function roleForCompany(?Company $company = null): ?Role
    {
        $companyId = $company?->id ?? $this->current_company_id;

        if (! $companyId) {
            return null;
        }

        return $this->memberships()
            ->where('company_id', $companyId)
            ->with('role.permissions')
            ->first()
            ?->role;
    }

    /**
     * @return array<int, string>
     */
    public function permissionsForCompany(?Company $company = null): array
    {
        if ($this->is_super_admin) {
            return Permission::query()
                ->pluck('slug')
                ->reject(fn ($slug) => $this->isMasterDataManagePermission($slug))
                ->values()
                ->all();
        }

        if ($this->isOwnerForCompany($company)) {
            return Permission::query()
                ->pluck('slug')
                ->values()
                ->all();
        }

        $role = $this->roleForCompany($company);

        if (! $role) {
            return [];
        }

        return $role->permissions->pluck('slug')->values()->all();
    }

    public function hasPermission(string $permission, ?Company $company = null): bool
    {
        if ($this->is_super_admin) {
            return in_array($permission, $this->permissionsForCompany($company), true);
        }

        return in_array($permission, $this->permissionsForCompany($company), true);
    }
}
