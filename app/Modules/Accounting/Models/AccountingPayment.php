<?php

namespace App\Modules\Accounting\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingPayment extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use CompanyScoped;
    use Auditable;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_REVERSED = 'reversed';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_POSTED,
        self::STATUS_RECONCILED,
        self::STATUS_REVERSED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'invoice_id',
        'payment_number',
        'status',
        'payment_date',
        'amount',
        'method',
        'reference',
        'posted_by',
        'posted_at',
        'reconciled_at',
        'reversed_by',
        'reversed_at',
        'reversal_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoice::class, 'invoice_id');
    }

    public function reconciliationEntries(): HasMany
    {
        return $this->hasMany(AccountingReconciliationEntry::class, 'payment_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
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
