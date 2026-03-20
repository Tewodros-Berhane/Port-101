<?php

namespace App\Modules\Accounting\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingReconciliationEntry extends Model
{
    use CompanyScoped;
    use HasFactory;
    use HasUuids;

    public const TYPE_APPLY = 'apply';

    public const TYPE_REVERSAL = 'reversal';

    /**
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_APPLY,
        self::TYPE_REVERSAL,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'invoice_id',
        'payment_id',
        'entry_type',
        'amount',
        'reconciled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reconciled_at' => 'datetime',
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(AccountingPayment::class, 'payment_id');
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
