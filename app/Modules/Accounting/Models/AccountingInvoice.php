<?php

namespace App\Modules\Accounting\Models;

use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Partner;
use App\Core\Support\Auditable;
use App\Core\Support\CompanyScoped;
use App\Models\User;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingInvoice extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use CompanyScoped;
    use Auditable;

    public const TYPE_CUSTOMER_INVOICE = 'customer_invoice';

    public const TYPE_VENDOR_BILL = 'vendor_bill';

    /**
     * @var array<int, string>
     */
    public const TYPES = [
        self::TYPE_CUSTOMER_INVOICE,
        self::TYPE_VENDOR_BILL,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_POSTED,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    public const DELIVERY_STATUS_NOT_REQUIRED = 'not_required';

    public const DELIVERY_STATUS_PENDING = 'pending_delivery';

    public const DELIVERY_STATUS_READY = 'ready_for_posting';

    /**
     * @var array<int, string>
     */
    public const DELIVERY_STATUSES = [
        self::DELIVERY_STATUS_NOT_REQUIRED,
        self::DELIVERY_STATUS_PENDING,
        self::DELIVERY_STATUS_READY,
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'partner_id',
        'sales_order_id',
        'purchase_order_id',
        'document_type',
        'invoice_number',
        'status',
        'delivery_status',
        'invoice_date',
        'due_date',
        'currency_code',
        'subtotal',
        'tax_total',
        'grand_total',
        'paid_total',
        'balance_due',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingInvoiceLine::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AccountingPayment::class, 'invoice_id');
    }

    public function reconciliationEntries(): HasMany
    {
        return $this->hasMany(AccountingReconciliationEntry::class, 'invoice_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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
