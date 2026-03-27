<?php

namespace App\Core\Platform\Models;

use App\Core\Company\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueFailureReview extends Model
{
    use HasFactory;
    use HasUuids;

    public const CLASSIFICATION_RETRYABLE = 'retryable';

    public const CLASSIFICATION_INVESTIGATE = 'investigate';

    public const CLASSIFICATION_POISON_SUSPECTED = 'poison_suspected';

    public const ACTION_RETRY = 'retry';

    public const ACTION_REVIEW = 'review_before_retry';

    public const ACTION_DISCARD = 'discard_as_poison';

    public const DECISION_DISCARDED = 'discarded_as_poison';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'failed_job_id',
        'queue',
        'connection',
        'job_name',
        'job_name_label',
        'request_id',
        'company_id',
        'exception_class',
        'exception_message',
        'classification',
        'recommended_action',
        'decision',
        'notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
