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

class AccountingAccount extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    public const NORMAL_DEBIT = 'debit';

    public const NORMAL_CREDIT = 'credit';

    public const CATEGORY_CASH = 'cash_and_bank';

    public const CATEGORY_RECEIVABLE = 'receivable';

    public const CATEGORY_TAX_ASSET = 'tax_asset';

    public const CATEGORY_PAYABLE = 'payable';

    public const CATEGORY_TAX_LIABILITY = 'tax_liability';

    public const CATEGORY_EQUITY = 'equity';

    public const CATEGORY_REVENUE = 'revenue';

    public const CATEGORY_EXPENSE = 'expense';

    public const SYSTEM_CASH_BANK = 'cash_bank';

    public const SYSTEM_ACCOUNTS_RECEIVABLE = 'accounts_receivable';

    public const SYSTEM_TAX_RECEIVABLE = 'tax_receivable';

    public const SYSTEM_ACCOUNTS_PAYABLE = 'accounts_payable';

    public const SYSTEM_SALES_TAX_PAYABLE = 'sales_tax_payable';

    public const SYSTEM_SALES_REVENUE = 'sales_revenue';

    public const SYSTEM_PURCHASE_EXPENSE = 'purchase_expense';

    public const SYSTEM_RETAINED_EARNINGS = 'retained_earnings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'account_type',
        'category',
        'normal_balance',
        'system_key',
        'is_active',
        'is_system',
        'allows_manual_posting',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'allows_manual_posting' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AccountingLedgerEntry::class, 'account_id');
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
