<?php

namespace App\Modules\Integrations\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use CompanyScoped;
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DEAD = 'dead';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_DELIVERED,
        self::STATUS_FAILED,
        self::STATUS_DEAD,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'webhook_endpoint_id',
        'integration_event_id',
        'event_type',
        'status',
        'attempt_count',
        'last_attempt_at',
        'next_retry_at',
        'response_status',
        'duration_ms',
        'response_body_excerpt',
        'failure_message',
        'delivered_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function integrationEvent(): BelongsTo
    {
        return $this->belongsTo(IntegrationEvent::class, 'integration_event_id');
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
