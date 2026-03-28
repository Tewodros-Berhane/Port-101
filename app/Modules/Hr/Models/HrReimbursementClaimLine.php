<?php

namespace App\Modules\Hr\Models;

use App\Core\Attachments\Models\Attachment;
use App\Core\Company\Models\Company;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrReimbursementClaimLine extends Model
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
        'claim_id',
        'category_id',
        'expense_date',
        'description',
        'amount',
        'tax_amount',
        'receipt_attachment_id',
        'project_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(HrReimbursementClaim::class, 'claim_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HrReimbursementCategory::class, 'category_id');
    }

    public function receiptAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'receipt_attachment_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
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
