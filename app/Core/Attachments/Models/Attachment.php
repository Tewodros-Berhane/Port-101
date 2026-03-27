<?php

namespace App\Core\Attachments\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const SCAN_PENDING = 'pending';

    public const SCAN_CLEAN = 'clean';

    public const SCAN_INFECTED = 'infected';

    public const SCAN_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'attachable_type',
        'attachable_id',
        'security_context',
        'disk',
        'path',
        'file_name',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'checksum',
        'scan_status',
        'scan_message',
        'scanned_at',
        'quarantined_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'scanned_at' => 'datetime',
            'quarantined_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
