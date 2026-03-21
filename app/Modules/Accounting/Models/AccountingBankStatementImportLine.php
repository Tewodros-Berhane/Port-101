<?php

namespace App\Modules\Accounting\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingBankStatementImportLine extends Model
{
    use CompanyScoped;
    use HasFactory;
    use HasUuids;

    public const MATCH_STATUS_MATCHED = 'matched';

    public const MATCH_STATUS_UNMATCHED = 'unmatched';

    public const MATCH_STATUS_DUPLICATE = 'duplicate';

    /**
     * @var array<int, string>
     */
    public const MATCH_STATUSES = [
        self::MATCH_STATUS_MATCHED,
        self::MATCH_STATUS_UNMATCHED,
        self::MATCH_STATUS_DUPLICATE,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'bank_statement_import_id',
        'line_number',
        'transaction_date',
        'reference',
        'description',
        'amount',
        'match_status',
        'payment_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(AccountingBankStatementImport::class, 'bank_statement_import_id');
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
