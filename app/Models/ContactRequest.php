<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactRequest extends Model
{
    use HasUuids;

    public const REQUEST_TYPE_DEMO = 'demo';

    public const REQUEST_TYPE_SALES = 'sales';

    /**
     * @var array<int, string>
     */
    public const REQUEST_TYPES = [
        self::REQUEST_TYPE_DEMO,
        self::REQUEST_TYPE_SALES,
    ];

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_DEMO_SCHEDULED = 'demo_scheduled';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<int, string>
     */
    public const STATUS_OPTIONS = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
        self::STATUS_DEMO_SCHEDULED,
        self::STATUS_CLOSED,
    ];

    /**
     * @var array<int, array{value: string, label: string}>
     */
    public const TEAM_SIZE_OPTIONS = [
        ['value' => '1-10', 'label' => '1-10'],
        ['value' => '11-50', 'label' => '11-50'],
        ['value' => '51-200', 'label' => '51-200'],
        ['value' => '201-500', 'label' => '201-500'],
        ['value' => '500+', 'label' => '500+'],
    ];

    /**
     * @var array<int, array{value: string, label: string}>
     */
    public const MODULE_OPTIONS = [
        ['value' => 'sales', 'label' => 'Sales'],
        ['value' => 'purchasing', 'label' => 'Purchasing'],
        ['value' => 'inventory', 'label' => 'Inventory'],
        ['value' => 'accounting', 'label' => 'Accounting'],
        ['value' => 'projects', 'label' => 'Projects'],
        ['value' => 'hr', 'label' => 'HR'],
        ['value' => 'approvals_reporting', 'label' => 'Approvals and reports'],
        ['value' => 'integrations_governance', 'label' => 'Integrations and governance'],
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_type',
        'full_name',
        'work_email',
        'company_name',
        'role_title',
        'team_size',
        'preferred_demo_date',
        'scheduled_demo_date',
        'demo_date_change_reason',
        'modules_interest',
        'message',
        'phone',
        'country',
        'source_page',
        'status',
        'assigned_to',
        'internal_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modules_interest' => 'array',
            'preferred_demo_date' => 'date',
            'scheduled_demo_date' => 'date',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
