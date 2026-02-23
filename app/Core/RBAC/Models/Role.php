<?php

namespace App\Core\RBAC\Models;

use App\Core\Approvals\Models\ApprovalAuthorityProfile;
use App\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'data_scope',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'data_scope' => 'string',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvalAuthorityProfiles(): HasMany
    {
        return $this->hasMany(ApprovalAuthorityProfile::class);
    }
}
