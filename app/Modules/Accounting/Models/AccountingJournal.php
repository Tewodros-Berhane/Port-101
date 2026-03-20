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

class AccountingJournal extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const TYPE_SALES = 'sales';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_BANK = 'bank';

    public const TYPE_GENERAL = 'general';

    public const SYSTEM_SALES = 'sales';

    public const SYSTEM_PURCHASE = 'purchase';

    public const SYSTEM_BANK = 'bank';

    public const SYSTEM_GENERAL = 'general';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'journal_type',
        'system_key',
        'default_account_id',
        'currency_code',
        'is_active',
        'is_system',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'default_account_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AccountingLedgerEntry::class, 'journal_id');
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
