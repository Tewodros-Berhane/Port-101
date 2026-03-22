<?php

namespace App\Modules\Reports\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use CompanyScoped;
    use HasFactory;
    use HasUuids;

    public const FORMAT_PDF = 'pdf';

    public const FORMAT_XLSX = 'xlsx';

    /**
     * @var array<int, string>
     */
    public const FORMATS = [
        self::FORMAT_PDF,
        self::FORMAT_XLSX,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'report_key',
        'report_title',
        'format',
        'status',
        'filters',
        'requested_by_user_id',
        'disk',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'row_count',
        'started_at',
        'completed_at',
        'failed_at',
        'expires_at',
        'failure_message',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'file_size' => 'integer',
            'row_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
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
