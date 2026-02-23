<?php

namespace App\Models;

use App\Core\Approvals\Models\ApprovalAuthorityProfile;
use App\Core\Company\Models\Company;
use App\Core\Company\Models\CompanyUser;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, TwoFactorAuthenticatable;

    public const DATA_SCOPE_OWN = 'own_records';

    public const DATA_SCOPE_TEAM = 'team_records';

    public const DATA_SCOPE_COMPANY = 'company_records';

    public const DATA_SCOPE_READ_ALL = 'read_all';

    /**
     * @var array<int, string>
     */
    public const DATA_SCOPE_OPTIONS = [
        self::DATA_SCOPE_OWN,
        self::DATA_SCOPE_TEAM,
        self::DATA_SCOPE_COMPANY,
        self::DATA_SCOPE_READ_ALL,
    ];

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
            'core.contacts',
            'core.addresses',
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

    public function approvalAuthorityProfiles(): HasMany
    {
        return $this->hasMany(ApprovalAuthorityProfile::class);
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function roleForCompany(?Company $company = null): ?Role
    {
        return $this->membershipForCompany($company)?->role;
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

    public function dataScopeForCompany(?Company $company = null): string
    {
        if ($this->is_super_admin) {
            return self::DATA_SCOPE_READ_ALL;
        }

        if ($this->isOwnerForCompany($company)) {
            return self::DATA_SCOPE_COMPANY;
        }

        return $this->normalizeDataScope($this->roleForCompany($company)?->data_scope);
    }

    public function canAccessDataScopedRecord(
        EloquentModel $record,
        ?Company $company = null
    ): bool {
        if ($this->is_super_admin) {
            return true;
        }

        $scope = $this->dataScopeForCompany($company);

        if ($scope === self::DATA_SCOPE_READ_ALL) {
            return true;
        }

        $companyId = $company?->id ?? $this->current_company_id;
        $recordCompanyId = $record->getAttribute('company_id');

        if (
            $companyId
            && $recordCompanyId
            && (string) $recordCompanyId !== (string) $companyId
        ) {
            return false;
        }

        if ($scope === self::DATA_SCOPE_COMPANY) {
            return true;
        }

        $createdBy = $record->getAttribute('created_by');

        if (! $createdBy) {
            return false;
        }

        if ((string) $createdBy === (string) $this->id) {
            return true;
        }

        if ($scope === self::DATA_SCOPE_OWN) {
            return false;
        }

        if ($scope !== self::DATA_SCOPE_TEAM) {
            return false;
        }

        return in_array((string) $createdBy, $this->teamMemberIdsForCompany($company), true);
    }

    public function applyDataScopeToQuery(Builder $query, ?Company $company = null): Builder
    {
        $scope = $this->dataScopeForCompany($company);

        if (
            in_array($scope, [self::DATA_SCOPE_COMPANY, self::DATA_SCOPE_READ_ALL], true)
        ) {
            return $query;
        }

        $createdByColumn = $query->getModel()->getTable().'.created_by';

        if ($scope === self::DATA_SCOPE_OWN) {
            return $query->where($createdByColumn, $this->id);
        }

        if ($scope === self::DATA_SCOPE_TEAM) {
            return $query->whereIn($createdByColumn, $this->teamMemberIdsForCompany($company));
        }

        return $query;
    }

    private function membershipForCompany(?Company $company = null): ?CompanyUser
    {
        $companyId = $company?->id ?? $this->current_company_id;

        if (! $companyId) {
            return null;
        }

        return $this->memberships()
            ->where('company_id', $companyId)
            ->with('role.permissions')
            ->first();
    }

    private function normalizeDataScope(?string $scope): string
    {
        if (! $scope) {
            return self::DATA_SCOPE_COMPANY;
        }

        return in_array($scope, self::DATA_SCOPE_OPTIONS, true)
            ? $scope
            : self::DATA_SCOPE_COMPANY;
    }

    /**
     * @return array<int, string>
     */
    private function teamMemberIdsForCompany(?Company $company = null): array
    {
        $companyId = $company?->id ?? $this->current_company_id;

        if (! $companyId) {
            return [(string) $this->id];
        }

        $membership = $this->memberships()
            ->where('company_id', $companyId)
            ->first();

        if (! $membership?->role_id) {
            return [(string) $this->id];
        }

        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('role_id', $membership->role_id)
            ->pluck('user_id')
            ->map(fn ($userId) => (string) $userId)
            ->push((string) $this->id)
            ->unique()
            ->values()
            ->all();
    }
}
