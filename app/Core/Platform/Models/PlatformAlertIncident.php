<?php

namespace App\Core\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformAlertIncident extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alert_key',
        'status',
        'severity',
        'title',
        'message',
        'metric_value',
        'threshold_value',
        'metadata',
        'first_triggered_at',
        'last_triggered_at',
        'last_notified_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'first_triggered_at' => 'datetime',
            'last_triggered_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
