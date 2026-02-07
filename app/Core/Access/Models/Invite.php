<?php

namespace App\Core\Access\Models;

use App\Core\Company\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
