<?php

namespace App\Modules\Projects\Models;

use App\Core\Company\Models\Company;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRecurringBillingRun extends Model
{
    use CompanyScoped;
    use HasFactory;
    use HasUuids;

    public const STATUS_READY = 'ready';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_FAILED = 'failed';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_READY,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_INVOICED,
        self::STATUS_FAILED,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'project_recurring_billing_id',
        'project_id',
        'cycle_key',
        'scheduled_for',
        'cycle_label',
        'status',
        'project_billable_id',
        'invoice_id',
        'processed_at',
        'error_message',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recurringBilling(): BelongsTo
    {
        return $this->belongsTo(ProjectRecurringBilling::class, 'project_recurring_billing_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function billable(): BelongsTo
    {
        return $this->belongsTo(ProjectBillable::class, 'project_billable_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoice::class, 'invoice_id');
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
