<?php

namespace App\Modules\Integrations\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WebhookEndpoint extends Model
{
    use CompanyScoped;
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'name',
        'target_url',
        'signing_secret',
        'signing_secret_version',
        'api_version',
        'is_active',
        'subscribed_events',
        'secret_rotated_at',
        'last_tested_at',
        'last_success_at',
        'last_failure_at',
        'last_delivery_at',
        'consecutive_failure_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'signing_secret_version' => 'integer',
            'subscribed_events' => 'array',
            'secret_rotated_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'last_delivery_at' => 'datetime',
            'consecutive_failure_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_endpoint_id');
    }

    public function latestDelivery(): HasOne
    {
        return $this->hasOne(WebhookDelivery::class, 'webhook_endpoint_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function secretRotations(): HasMany
    {
        return $this->hasMany(WebhookSecretRotation::class, 'webhook_endpoint_id');
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
