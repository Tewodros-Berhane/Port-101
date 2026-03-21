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

class AccountingBankStatementImport extends Model
{
    use Auditable;
    use CompanyScoped;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'journal_id',
        'reconciled_batch_id',
        'statement_reference',
        'statement_date',
        'source_file_name',
        'notes',
        'imported_by',
        'imported_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'imported_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'journal_id');
    }

    public function reconciledBatch(): BelongsTo
    {
        return $this->belongsTo(AccountingBankReconciliationBatch::class, 'reconciled_batch_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingBankStatementImportLine::class, 'bank_statement_import_id')
            ->orderBy('line_number');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
