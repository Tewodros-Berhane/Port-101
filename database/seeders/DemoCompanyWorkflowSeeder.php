<?php

namespace Database\Seeders;

use App\Core\Access\Models\Invite;
use App\Core\Approvals\Models\ApprovalAuthorityProfile;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Core\Company\Models\CompanyUser;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Contact;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Core\RBAC\Models\Role;
use App\Core\Settings\Models\Setting;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingInvoiceLine;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Accounting\Models\AccountingReconciliationEntry;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Approvals\Models\ApprovalStep;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Inventory\Models\InventoryWarehouse;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\PurchaseRfq;
use App\Modules\Purchasing\Models\PurchaseRfqLine;
use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Models\SalesQuote;
use App\Modules\Sales\Models\SalesQuoteLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoCompanyWorkflowSeeder extends Seeder
{
    private const DEMO_COMPANY_SLUG = 'demo-company-workflow';

    private const SALES_WORKFLOW_COUNT = 20;

    private const PURCHASING_WORKFLOW_COUNT = 20;

    private CarbonImmutable $now;

    /**
     * @var array<string, array{name: string, email: string}>
     */
    private array $roleUserBlueprints = [
        'owner' => ['name' => 'Olivia Owner', 'email' => 'owner@demo.port101.test'],
        'member' => ['name' => 'Mason Member', 'email' => 'member@demo.port101.test'],
        'operations_admin' => ['name' => 'Avery Operations', 'email' => 'operations.admin@demo.port101.test'],
        'sales_manager' => ['name' => 'Sophie Sales Manager', 'email' => 'sales.manager@demo.port101.test'],
        'sales_user' => ['name' => 'Samuel Sales User', 'email' => 'sales.user@demo.port101.test'],
        'inventory_manager' => ['name' => 'Ivy Inventory Manager', 'email' => 'inventory.manager@demo.port101.test'],
        'warehouse_clerk' => ['name' => 'Warren Warehouse Clerk', 'email' => 'warehouse.clerk@demo.port101.test'],
        'purchasing_manager' => ['name' => 'Paula Purchasing Manager', 'email' => 'purchasing.manager@demo.port101.test'],
        'buyer' => ['name' => 'Ben Buyer', 'email' => 'buyer@demo.port101.test'],
        'finance_manager' => ['name' => 'Fiona Finance Manager', 'email' => 'finance.manager@demo.port101.test'],
        'accountant' => ['name' => 'Andrew Accountant', 'email' => 'accountant@demo.port101.test'],
        'approver' => ['name' => 'Amina Approver', 'email' => 'approver@demo.port101.test'],
        'auditor' => ['name' => 'Adam Auditor', 'email' => 'auditor@demo.port101.test'],
    ];

    public function run(): void
    {
        $this->now = CarbonImmutable::now();
        $this->call(CoreRolesSeeder::class);

        $password = '123123123';
        $hashedPassword = Hash::make($password);
        $seededUsers = [];

        DB::transaction(function () use (&$seededUsers, $hashedPassword): void {
            [$company, $usersByRole, $rolesBySlug] = $this->seedCompanyAndUsers($hashedPassword);
            $seededUsers = $usersByRole;

            $master = $this->seedMasterData($company, $usersByRole);
            $invites = $this->seedInvites($company, $usersByRole);
            $this->seedCompanySettings($company, $usersByRole['owner']);
            $sales = $this->seedSales($company, $usersByRole, $master);
            $purchasing = $this->seedPurchasing($company, $usersByRole, $master);
            $inventory = $this->seedInventory($company, $usersByRole, $master['products'], $sales['orders'], $purchasing['orders']);
            $accounting = $this->seedAccounting($company, $usersByRole, $sales['orders'], $purchasing['orders']);
            $this->seedApprovals($company, $usersByRole, $rolesBySlug, $sales['quotes'], $sales['orders'], $purchasing['orders']);
            $this->seedNotifications($usersByRole);
            $this->seedAuditLogs($company, $usersByRole, $sales, $purchasing, $accounting, $inventory, $invites);
        });

        if ($this->command) {
            $this->command->info('Demo company workflow data seeded successfully.');
            $this->command->line('All demo users use password: 123123123');
            foreach ($this->roleUserBlueprints as $slug => $blueprint) {
                $user = $seededUsers[$slug] ?? null;
                if ($user) {
                    $this->command->line(sprintf('- %-18s %s', $slug, $user->email));
                }
            }
        }
    }

    /**
     * @return array{0: Company, 1: array<string, User>, 2: array<string, Role>}
     */
    private function seedCompanyAndUsers(string $hashedPassword): array
    {
        $slugs = array_keys($this->roleUserBlueprints);
        $roles = Role::query()->whereNull('company_id')->whereIn('slug', $slugs)->get()->keyBy('slug');

        if ($roles->count() !== count($slugs)) {
            $missing = array_values(array_diff($slugs, $roles->keys()->all()));
            throw new RuntimeException('Missing global roles: '.implode(', ', $missing));
        }

        $ownerBlueprint = $this->roleUserBlueprints['owner'];
        $owner = $this->upsertUser($ownerBlueprint['name'], $ownerBlueprint['email'], $hashedPassword, null);

        $company = Company::query()->firstOrNew(['slug' => self::DEMO_COMPANY_SLUG]);
        $company->forceFill([
            'name' => 'Port-101 Demo Trading PLC',
            'slug' => self::DEMO_COMPANY_SLUG,
            'timezone' => 'America/New_York',
            'currency_code' => 'USD',
            'is_active' => true,
            'owner_id' => $owner->id,
        ])->save();

        $users = [];
        foreach ($this->roleUserBlueprints as $slug => $blueprint) {
            $user = $this->upsertUser($blueprint['name'], $blueprint['email'], $hashedPassword, $company->id);
            CompanyUser::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                ['role_id' => $roles[$slug]->id, 'is_owner' => $slug === 'owner']
            );
            $users[$slug] = $user;
        }

        $company->forceFill(['owner_id' => $users['owner']->id])->save();

        return [$company->refresh(), $users, $roles->all()];
    }

    private function upsertUser(string $name, string $email, string $hashedPassword, ?string $companyId): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'current_company_id' => $companyId,
            'locale' => 'en',
            'timezone' => 'America/New_York',
            'email_verified_at' => $user->email_verified_at ?? $this->now,
            'is_super_admin' => false,
            'remember_token' => $user->remember_token ?: Str::random(10),
        ])->save();

        return $user->refresh();
    }

    /**
     * @param  array<string, User>  $usersByRole
     */
    private function seedCompanySettings(Company $company, User $owner): void
    {
        $settings = [
            'company.fiscal_year_start' => '2026-01-01',
            'company.locale' => 'en-US',
            'company.date_format' => 'Y-m-d',
            'company.number_format' => '1,234.56',
            'core.audit_logs.retention_days' => 365,
            'company.tax_period' => 'monthly',
            'company.tax_submission_day' => 20,
            'company.approvals.enabled' => true,
            'company.approvals.policy' => 'threshold',
            'company.approvals.threshold_amount' => 7500,
            'company.approvals.escalation_hours' => 24,
            'company.numbering.sales_order_prefix' => 'SO',
            'company.numbering.sales_order_next' => 2201,
            'company.numbering.purchase_order_prefix' => 'PO',
            'company.numbering.purchase_order_next' => 1801,
            'company.numbering.invoice_prefix' => 'INV',
            'company.numbering.invoice_next' => 2501,
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(
                ['company_id' => $company->id, 'user_id' => null, 'key' => $key],
                ['value' => $value, 'created_by' => $owner->id, 'updated_by' => $owner->id]
            );
        }
    }

    /**
     * @param  array<string, User>  $usersByRole
     * @return array{
     *   customers: array<int, Partner>,
     *   vendors: array<int, Partner>,
     *   products: array<int, Product>,
     *   default_tax_rate: float
     * }
     */
    private function seedMasterData(Company $company, array $usersByRole): array
    {
        $opsId = $usersByRole['operations_admin']->id;
        $salesId = $usersByRole['sales_manager']->id;
        $purchasingId = $usersByRole['purchasing_manager']->id;
        $inventoryId = $usersByRole['inventory_manager']->id;

        $usd = Currency::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'created_by' => $opsId,
                'updated_by' => $opsId,
            ]
        );

        Currency::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'EUR'],
            [
                'name' => 'Euro',
                'symbol' => 'EUR',
                'decimal_places' => 2,
                'is_active' => true,
                'created_by' => $opsId,
                'updated_by' => $opsId,
            ]
        );

        $uomNames = [['Unit', 'pc'], ['Box', 'box'], ['Pallet', 'plt'], ['Hour', 'hr']];
        $uoms = [];
        foreach ($uomNames as [$name, $symbol]) {
            $uoms[$name] = Uom::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                [
                    'symbol' => $symbol,
                    'is_active' => true,
                    'created_by' => $opsId,
                    'updated_by' => $opsId,
                ]
            );
        }

        $tax = Tax::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'VAT 15%'],
            [
                'type' => 'percent',
                'rate' => 15,
                'is_active' => true,
                'created_by' => $opsId,
                'updated_by' => $opsId,
            ]
        );

        Tax::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Zero VAT'],
            [
                'type' => 'percent',
                'rate' => 0,
                'is_active' => true,
                'created_by' => $opsId,
                'updated_by' => $opsId,
            ]
        );

        PriceList::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Standard USD'],
            ['currency_id' => $usd->id, 'is_active' => true, 'created_by' => $opsId, 'updated_by' => $opsId]
        );

        PriceList::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Wholesale USD'],
            ['currency_id' => $usd->id, 'is_active' => true, 'created_by' => $opsId, 'updated_by' => $opsId]
        );

        $customers = [];
        $vendors = [];

        for ($i = 1; $i <= 20; $i++) {
            $customer = Partner::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => sprintf('CUS-%03d', $i)],
                [
                    'name' => sprintf('Customer %02d Retail Group', $i),
                    'type' => 'customer',
                    'email' => sprintf('customer%02d@example.test', $i),
                    'phone' => sprintf('+1-555-110-%04d', $i),
                    'is_active' => true,
                    'created_by' => $salesId,
                    'updated_by' => $salesId,
                ]
            );
            $this->seedPartnerContactAndAddress($company, $customer, $salesId, 'billing');
            $this->seedPartnerContactAndAddress($company, $customer, $salesId, 'shipping');
            $customers[] = $customer;

            $vendor = Partner::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => sprintf('VEN-%03d', $i)],
                [
                    'name' => sprintf('Vendor %02d Supply House', $i),
                    'type' => 'vendor',
                    'email' => sprintf('vendor%02d@example.test', $i),
                    'phone' => sprintf('+1-555-210-%04d', $i),
                    'is_active' => true,
                    'created_by' => $purchasingId,
                    'updated_by' => $purchasingId,
                ]
            );
            $this->seedPartnerContactAndAddress($company, $vendor, $purchasingId, 'billing');
            $this->seedPartnerContactAndAddress($company, $vendor, $purchasingId, 'shipping');
            $vendors[] = $vendor;
        }

        for ($i = 1; $i <= 5; $i++) {
            $both = Partner::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => sprintf('BTH-%03d', $i)],
                [
                    'name' => sprintf('Partner %02d Omni Trade', $i),
                    'type' => 'both',
                    'email' => sprintf('partner%02d@example.test', $i),
                    'phone' => sprintf('+1-555-310-%04d', $i),
                    'is_active' => true,
                    'created_by' => $opsId,
                    'updated_by' => $opsId,
                ]
            );
            $this->seedPartnerContactAndAddress($company, $both, $opsId, 'billing');
            $this->seedPartnerContactAndAddress($company, $both, $opsId, 'shipping');
            $customers[] = $both;
            $vendors[] = $both;
        }

        $families = ['Router', 'Switch', 'Firewall', 'Scanner', 'Laptop', 'Monitor', 'Cable', 'Sensor', 'Rack', 'Battery'];
        $products = [];
        for ($i = 1; $i <= 30; $i++) {
            $family = $families[($i - 1) % count($families)];
            $service = $i % 7 === 0;
            $uom = $service ? $uoms['Hour'] : $uoms['Unit'];
            $products[] = Product::query()->updateOrCreate(
                ['company_id' => $company->id, 'sku' => sprintf('SKU-%04d', $i)],
                [
                    'name' => sprintf('%s %02d', $family, $i),
                    'type' => $service ? 'service' : 'stock',
                    'uom_id' => $uom->id,
                    'default_tax_id' => $tax->id,
                    'description' => sprintf('Demo catalog item %02d.', $i),
                    'is_active' => true,
                    'created_by' => $inventoryId,
                    'updated_by' => $inventoryId,
                ]
            );
        }

        return ['customers' => $customers, 'vendors' => $vendors, 'products' => $products, 'default_tax_rate' => (float) $tax->rate];
    }

    private function seedPartnerContactAndAddress(Company $company, Partner $partner, string $actorId, string $type): void
    {
        Contact::query()->updateOrCreate(
            ['company_id' => $company->id, 'partner_id' => $partner->id, 'name' => $partner->name.' Contact'],
            [
                'email' => $partner->email,
                'phone' => $partner->phone,
                'title' => 'Operations',
                'is_primary' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );

        Address::query()->updateOrCreate(
            ['company_id' => $company->id, 'partner_id' => $partner->id, 'type' => $type],
            [
                'line1' => sprintf('%d Demo Logistics Park', random_int(100, 980)),
                'line2' => 'Suite '.random_int(10, 40),
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => sprintf('10%03d', random_int(10, 980)),
                'country_code' => 'US',
                'is_primary' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );
    }

    /**
     * @param  array<string, User>  $usersByRole
     * @return array<int, Invite>
     */
    private function seedInvites(Company $company, array $usersByRole): array
    {
        $slugs = array_keys($this->roleUserBlueprints);
        $invites = [];

        for ($i = 1; $i <= 16; $i++) {
            $status = match ($i % 4) {
                0 => Invite::DELIVERY_FAILED,
                1 => Invite::DELIVERY_PENDING,
                default => Invite::DELIVERY_SENT,
            };
            $acceptedAt = $i % 5 === 0 ? $this->now->subDays(20 - $i) : null;
            $expiresAt = $acceptedAt ? $this->now->addDays(14) : ($i % 6 === 0 ? $this->now->subDay() : $this->now->addDays(7 + $i));

            $invites[] = Invite::query()->updateOrCreate(
                ['token' => hash('sha256', 'demo-company-invite-'.$i)],
                [
                    'email' => sprintf('invite%02d@demo.port101.test', $i),
                    'name' => sprintf('Invited User %02d', $i),
                    'role' => $slugs[($i - 1) % count($slugs)],
                    'company_id' => $company->id,
                    'expires_at' => $expiresAt,
                    'accepted_at' => $acceptedAt,
                    'delivery_status' => $status,
                    'delivery_attempts' => $status === Invite::DELIVERY_FAILED ? 3 : 1,
                    'last_delivery_at' => $status === Invite::DELIVERY_PENDING ? null : $this->now->subHours($i),
                    'last_delivery_error' => $status === Invite::DELIVERY_FAILED ? 'SMTP timeout from demo seed run' : null,
                    'created_by' => $usersByRole['owner']->id,
                ]
            );
        }

        return $invites;
    }

    /**
     * @param  array<string, User>  $usersByRole
     * @param  array{
     *   customers: array<int, Partner>,
     *   products: array<int, Product>,
     *   default_tax_rate: float
     * }  $master
     * @return array{
     *   leads: array<int, SalesLead>,
     *   quotes: array<int, SalesQuote>,
     *   orders: array<int, SalesOrder>
     * }
     */
    private function seedSales(Company $company, array $usersByRole, array $master): array
    {
        $customers = $master['customers'];
        $products = $master['products'];
        $taxRate = $master['default_tax_rate'];

        $leadStages = ['new', 'qualified', 'quoted', 'won', 'lost'];
        $quoteCycle = [SalesQuote::STATUS_DRAFT, SalesQuote::STATUS_SENT, SalesQuote::STATUS_APPROVED, SalesQuote::STATUS_CONFIRMED];
        $orderCycle = [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_FULFILLED, SalesOrder::STATUS_INVOICED, SalesOrder::STATUS_CLOSED];

        $leads = [];
        $quotes = [];
        $orders = [];

        for ($i = 1; $i <= self::SALES_WORKFLOW_COUNT; $i++) {
            $customer = $customers[($i - 1) % count($customers)];
            $createdBy = $i % 2 === 0 ? $usersByRole['sales_manager']->id : $usersByRole['sales_user']->id;
            $updatedBy = $usersByRole['sales_manager']->id;
            $stage = $leadStages[($i - 1) % count($leadStages)];

            $lead = SalesLead::query()->updateOrCreate(
                ['company_id' => $company->id, 'title' => sprintf('Demo Sales Lead %02d', $i)],
                [
                    'partner_id' => $customer->id,
                    'stage' => $stage,
                    'estimated_value' => 2500 + ($i * 620),
                    'expected_close_date' => $this->now->addDays(10 + $i)->toDateString(),
                    'notes' => 'Seeded lead for pipeline and conversion coverage.',
                    'converted_at' => in_array($stage, ['quoted', 'won'], true) ? $this->now->subDays(12) : null,
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]
            );

            $lineA = $this->buildSalesLine($products[($i - 1) % count($products)], 2 + ($i % 4), 160 + ($i * 12), ($i % 3) * 2.5, $taxRate, 'Primary line');
            $lineB = $this->buildSalesLine($products[($i + 4) % count($products)], 1 + ($i % 3), 95 + ($i * 8), ($i % 2) * 1.5, $taxRate, 'Secondary line');
            $totals = $this->calculateDocumentTotals([$lineA, $lineB]);

            $quoteStatus = $stage === 'lost' ? SalesQuote::STATUS_REJECTED : $quoteCycle[($i - 1) % count($quoteCycle)];
            $quoteRequiresApproval = $totals['grand_total'] >= 7000 || $i % 5 === 0;
            $quoteApproved = in_array($quoteStatus, [SalesQuote::STATUS_APPROVED, SalesQuote::STATUS_CONFIRMED], true);

            $quote = SalesQuote::query()->updateOrCreate(
                ['company_id' => $company->id, 'quote_number' => sprintf('SQ-2026-%04d', $i)],
                [
                    'lead_id' => $lead->id,
                    'partner_id' => $customer->id,
                    'status' => $quoteStatus,
                    'quote_date' => $this->now->subDays(34 - $i)->toDateString(),
                    'valid_until' => $this->now->addDays(20)->toDateString(),
                    'subtotal' => $totals['subtotal'],
                    'discount_total' => $totals['discount_total'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'requires_approval' => $quoteRequiresApproval,
                    'approved_by' => $quoteApproved ? $usersByRole['approver']->id : null,
                    'approved_at' => $quoteApproved ? $this->now->subDays(3) : null,
                    'rejection_reason' => $quoteStatus === SalesQuote::STATUS_REJECTED ? 'Seeded rejection scenario.' : null,
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]
            );

            SalesQuoteLine::query()->where('quote_id', $quote->id)->delete();
            foreach ([$lineA, $lineB] as $line) {
                SalesQuoteLine::query()->create([
                    'company_id' => $company->id,
                    'quote_id' => $quote->id,
                    'product_id' => $line['product_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'],
                    'tax_rate' => $line['tax_rate'],
                    'line_subtotal' => $line['line_subtotal'],
                    'line_total' => $line['line_total'],
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]);
            }

            $orderStatus = match (true) {
                $quoteStatus === SalesQuote::STATUS_REJECTED => SalesOrder::STATUS_CANCELLED,
                in_array($quoteStatus, [SalesQuote::STATUS_DRAFT, SalesQuote::STATUS_SENT], true) => SalesOrder::STATUS_DRAFT,
                default => $orderCycle[($i - 1) % count($orderCycle)],
            };
            $orderRequiresApproval = $totals['grand_total'] >= 8500 || $i % 6 === 0;
            $orderApproved = in_array($orderStatus, [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_FULFILLED, SalesOrder::STATUS_INVOICED, SalesOrder::STATUS_CLOSED], true);

            $order = SalesOrder::query()->updateOrCreate(
                ['company_id' => $company->id, 'order_number' => sprintf('SO-2026-%04d', $i)],
                [
                    'quote_id' => $quote->id,
                    'partner_id' => $customer->id,
                    'status' => $orderStatus,
                    'order_date' => $this->now->subDays(29 - $i)->toDateString(),
                    'subtotal' => $totals['subtotal'],
                    'discount_total' => $totals['discount_total'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'requires_approval' => $orderRequiresApproval,
                    'approved_by' => $orderApproved && $orderRequiresApproval ? $usersByRole['approver']->id : null,
                    'approved_at' => $orderApproved && $orderRequiresApproval ? $this->now->subDays(2) : null,
                    'confirmed_by' => $orderApproved ? $usersByRole['sales_manager']->id : null,
                    'confirmed_at' => $orderApproved ? $this->now->subDays(2) : null,
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]
            );

            SalesOrderLine::query()->where('order_id', $order->id)->delete();
            foreach ([$lineA, $lineB] as $line) {
                SalesOrderLine::query()->create([
                    'company_id' => $company->id,
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'],
                    'tax_rate' => $line['tax_rate'],
                    'line_subtotal' => $line['line_subtotal'],
                    'line_total' => $line['line_total'],
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]);
            }

            $leads[] = $lead;
            $quotes[] = $quote;
            $orders[] = $order;
        }

        return ['leads' => $leads, 'quotes' => $quotes, 'orders' => $orders];
    }

    /**
     * @return array{
     *   product_id: string,
     *   description: string,
     *   quantity: float,
     *   unit_price: float,
     *   discount_percent: float,
     *   tax_rate: float,
     *   line_subtotal: float,
     *   line_total: float,
     *   discount_amount: float,
     *   tax_amount: float
     * }
     */
    private function buildSalesLine(Product $product, float $qty, float $unitPrice, float $discount, float $taxRate, string $suffix): array
    {
        $rawSubtotal = round($qty * $unitPrice, 2);
        $discountAmount = round($rawSubtotal * ($discount / 100), 2);
        $lineSubtotal = round($rawSubtotal - $discountAmount, 2);
        $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);
        $lineTotal = round($lineSubtotal + $taxAmount, 2);

        return [
            'product_id' => (string) $product->id,
            'description' => $product->name.' - '.$suffix,
            'quantity' => round($qty, 4),
            'unit_price' => round($unitPrice, 2),
            'discount_percent' => round($discount, 2),
            'tax_rate' => round($taxRate, 2),
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
        ];
    }

    /**
     * @param  array<int, array{
     *   line_subtotal: float,
     *   line_total: float,
     *   discount_amount: float,
     *   tax_amount: float
     * }>  $lines
     * @return array{
     *   subtotal: float,
     *   discount_total: float,
     *   tax_total: float,
     *   grand_total: float
     * }
     */
    private function calculateDocumentTotals(array $lines): array
    {
        return [
            'subtotal' => round((float) array_sum(array_column($lines, 'line_subtotal')), 2),
            'discount_total' => round((float) array_sum(array_column($lines, 'discount_amount')), 2),
            'tax_total' => round((float) array_sum(array_column($lines, 'tax_amount')), 2),
            'grand_total' => round((float) array_sum(array_column($lines, 'line_total')), 2),
        ];
    }

    /**
     * @param  array<string, User>  $usersByRole
     * @param  array{
     *   vendors: array<int, Partner>,
     *   products: array<int, Product>,
     *   default_tax_rate: float
     * }  $master
     * @return array{
     *   rfqs: array<int, PurchaseRfq>,
     *   orders: array<int, PurchaseOrder>
     * }
     */
    private function seedPurchasing(Company $company, array $usersByRole, array $master): array
    {
        $vendors = $master['vendors'];
        $products = $master['products'];
        $taxRate = $master['default_tax_rate'];

        $rfqStatuses = [PurchaseRfq::STATUS_DRAFT, PurchaseRfq::STATUS_SENT, PurchaseRfq::STATUS_VENDOR_RESPONDED, PurchaseRfq::STATUS_SELECTED, PurchaseRfq::STATUS_CANCELLED];
        $poCycle = [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED];

        $rfqs = [];
        $orders = [];

        for ($i = 1; $i <= self::PURCHASING_WORKFLOW_COUNT; $i++) {
            $vendor = $vendors[($i - 1) % count($vendors)];
            $createdBy = $i % 2 === 0 ? $usersByRole['purchasing_manager']->id : $usersByRole['buyer']->id;
            $updatedBy = $usersByRole['purchasing_manager']->id;
            $rfqStatus = $rfqStatuses[($i - 1) % count($rfqStatuses)];

            $lineA = $this->buildPurchaseLine($products[($i + 1) % count($products)], 3 + ($i % 3), 75 + ($i * 7), $taxRate, 'Main procurement line');
            $lineB = $this->buildPurchaseLine($products[($i + 7) % count($products)], 2 + ($i % 2), 45 + ($i * 5), $taxRate, 'Supporting procurement line');
            $totals = $this->calculateDocumentTotals([$lineA, $lineB]);

            $rfq = PurchaseRfq::query()->updateOrCreate(
                ['company_id' => $company->id, 'rfq_number' => sprintf('RFQ-2026-%04d', $i)],
                [
                    'partner_id' => $vendor->id,
                    'status' => $rfqStatus,
                    'rfq_date' => $this->now->subDays(33 - $i)->toDateString(),
                    'valid_until' => $this->now->addDays(18)->toDateString(),
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'sent_at' => in_array($rfqStatus, [PurchaseRfq::STATUS_SENT, PurchaseRfq::STATUS_VENDOR_RESPONDED, PurchaseRfq::STATUS_SELECTED], true) ? $this->now->subDays(8) : null,
                    'vendor_responded_at' => in_array($rfqStatus, [PurchaseRfq::STATUS_VENDOR_RESPONDED, PurchaseRfq::STATUS_SELECTED], true) ? $this->now->subDays(5) : null,
                    'selected_at' => $rfqStatus === PurchaseRfq::STATUS_SELECTED ? $this->now->subDays(3) : null,
                    'notes' => 'Seeded RFQ for procurement workflow.',
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]
            );

            PurchaseRfqLine::query()->where('rfq_id', $rfq->id)->delete();
            foreach ([$lineA, $lineB] as $line) {
                PurchaseRfqLine::query()->create([
                    'company_id' => $company->id,
                    'rfq_id' => $rfq->id,
                    'product_id' => $line['product_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'line_subtotal' => $line['line_subtotal'],
                    'line_total' => $line['line_total'],
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]);
            }

            $poStatus = $rfqStatus === PurchaseRfq::STATUS_CANCELLED ? PurchaseOrder::STATUS_CANCELLED : $poCycle[($i - 1) % count($poCycle)];
            $requiresApproval = $totals['grand_total'] >= 7000 || $i % 4 === 0;
            $approved = in_array($poStatus, [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED], true);

            $po = PurchaseOrder::query()->updateOrCreate(
                ['company_id' => $company->id, 'order_number' => sprintf('PO-2026-%04d', $i)],
                [
                    'rfq_id' => $rfq->id,
                    'partner_id' => $vendor->id,
                    'status' => $poStatus,
                    'order_date' => $this->now->subDays(30 - $i)->toDateString(),
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'requires_approval' => $requiresApproval,
                    'approved_by' => $approved && $requiresApproval ? $usersByRole['approver']->id : null,
                    'approved_at' => $approved && $requiresApproval ? $this->now->subDays(3) : null,
                    'ordered_by' => in_array($poStatus, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED], true) ? $usersByRole['buyer']->id : null,
                    'ordered_at' => in_array($poStatus, [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED], true) ? $this->now->subDays(5) : null,
                    'received_at' => in_array($poStatus, [PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED], true) ? $this->now->subDays(2) : null,
                    'billed_at' => in_array($poStatus, [PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED], true) ? $this->now->subDay() : null,
                    'closed_at' => $poStatus === PurchaseOrder::STATUS_CLOSED ? $this->now->subHours(12) : null,
                    'notes' => 'Seeded purchase order for AP workflow coverage.',
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]
            );

            PurchaseOrderLine::query()->where('order_id', $po->id)->delete();
            $ratio = match ($poStatus) {
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 0.55,
                PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED => 1.0,
                default => 0.0,
            };
            foreach ([$lineA, $lineB] as $line) {
                PurchaseOrderLine::query()->create([
                    'company_id' => $company->id,
                    'order_id' => $po->id,
                    'product_id' => $line['product_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'received_quantity' => round($line['quantity'] * $ratio, 4),
                    'unit_cost' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'line_subtotal' => $line['line_subtotal'],
                    'line_total' => $line['line_total'],
                    'created_by' => $createdBy,
                    'updated_by' => $updatedBy,
                ]);
            }

            $rfqs[] = $rfq;
            $orders[] = $po;
        }

        return ['rfqs' => $rfqs, 'orders' => $orders];
    }

    /**
     * @return array{
     *   product_id: string,
     *   description: string,
     *   quantity: float,
     *   unit_price: float,
     *   discount_percent: float,
     *   tax_rate: float,
     *   line_subtotal: float,
     *   line_total: float,
     *   discount_amount: float,
     *   tax_amount: float
     * }
     */
    private function buildPurchaseLine(Product $product, float $qty, float $unitCost, float $taxRate, string $suffix): array
    {
        $lineSubtotal = round($qty * $unitCost, 2);
        $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);

        return [
            'product_id' => (string) $product->id,
            'description' => $product->name.' - '.$suffix,
            'quantity' => round($qty, 4),
            'unit_price' => round($unitCost, 2),
            'discount_percent' => 0.0,
            'tax_rate' => round($taxRate, 2),
            'line_subtotal' => $lineSubtotal,
            'line_total' => round($lineSubtotal + $taxAmount, 2),
            'discount_amount' => 0.0,
            'tax_amount' => $taxAmount,
        ];
    }

    /**
     * @param  array<string, User>  $usersByRole
     * @param  array<int, Product>  $products
     * @param  array<int, SalesOrder>  $salesOrders
     * @param  array<int, PurchaseOrder>  $purchaseOrders
     * @return array{
     *   warehouses: array<int, InventoryWarehouse>,
     *   locations: array<int, InventoryLocation>,
     *   moves: array<int, InventoryStockMove>
     * }
     */
    private function seedInventory(
        Company $company,
        array $usersByRole,
        array $products,
        array $salesOrders,
        array $purchaseOrders
    ): array {
        $inventoryId = $usersByRole['inventory_manager']->id;
        $clerkId = $usersByRole['warehouse_clerk']->id;

        $mainWarehouse = InventoryWarehouse::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'WH-MAIN'],
            ['name' => 'Main Distribution Center', 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );
        $reserveWarehouse = InventoryWarehouse::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'WH-RES'],
            ['name' => 'Reserve Storage', 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );

        $loc = [];
        $loc['stock'] = InventoryLocation::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'LOC-STOCK'],
            ['warehouse_id' => $mainWarehouse->id, 'name' => 'Main Stock', 'type' => InventoryLocation::TYPE_INTERNAL, 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );
        $loc['input'] = InventoryLocation::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'LOC-INPUT'],
            ['warehouse_id' => $mainWarehouse->id, 'name' => 'Goods Input', 'type' => InventoryLocation::TYPE_INTERNAL, 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );
        $loc['output'] = InventoryLocation::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'LOC-OUTPUT'],
            ['warehouse_id' => $mainWarehouse->id, 'name' => 'Outbound Dock', 'type' => InventoryLocation::TYPE_INTERNAL, 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );
        $loc['reserve'] = InventoryLocation::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'LOC-RESERVE'],
            ['warehouse_id' => $reserveWarehouse->id, 'name' => 'Reserve Stock', 'type' => InventoryLocation::TYPE_INTERNAL, 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );
        $loc['transit'] = InventoryLocation::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'LOC-TRANSIT'],
            ['warehouse_id' => null, 'name' => 'Transit Zone', 'type' => InventoryLocation::TYPE_TRANSIT, 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );
        $loc['vendor'] = InventoryLocation::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'LOC-VENDOR'],
            ['warehouse_id' => null, 'name' => 'Vendor Location', 'type' => InventoryLocation::TYPE_VENDOR, 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );
        $loc['customer'] = InventoryLocation::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'LOC-CUSTOMER'],
            ['warehouse_id' => null, 'name' => 'Customer Location', 'type' => InventoryLocation::TYPE_CUSTOMER, 'is_active' => true, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
        );

        $stockProducts = array_values(array_filter($products, fn (Product $product) => $product->type === 'stock'));
        foreach ($stockProducts as $idx => $product) {
            $onHand = 65 + (($idx * 11) % 180);
            $reserved = min($onHand - 1, (($idx + 3) * 2) % 22);
            InventoryStockLevel::query()->updateOrCreate(
                ['company_id' => $company->id, 'location_id' => $loc['stock']->id, 'product_id' => $product->id],
                ['on_hand_quantity' => $onHand, 'reserved_quantity' => $reserved, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
            );

            if ($idx % 3 === 0) {
                InventoryStockLevel::query()->updateOrCreate(
                    ['company_id' => $company->id, 'location_id' => $loc['reserve']->id, 'product_id' => $product->id],
                    ['on_hand_quantity' => 20 + (($idx * 4) % 40), 'reserved_quantity' => 0, 'created_by' => $inventoryId, 'updated_by' => $inventoryId]
                );
            }
        }

        $moves = [];
        foreach ($salesOrders as $order) {
            $line = SalesOrderLine::query()->where('order_id', $order->id)->orderBy('created_at')->first();
            if (! $line) {
                continue;
            }
            $status = match ($order->status) {
                SalesOrder::STATUS_CONFIRMED => InventoryStockMove::STATUS_RESERVED,
                SalesOrder::STATUS_FULFILLED, SalesOrder::STATUS_INVOICED, SalesOrder::STATUS_CLOSED => InventoryStockMove::STATUS_DONE,
                SalesOrder::STATUS_CANCELLED => InventoryStockMove::STATUS_CANCELLED,
                default => InventoryStockMove::STATUS_DRAFT,
            };
            $moves[] = InventoryStockMove::query()->updateOrCreate(
                ['company_id' => $company->id, 'reference' => 'DEL-'.$order->order_number],
                [
                    'move_type' => InventoryStockMove::TYPE_DELIVERY,
                    'status' => $status,
                    'source_location_id' => $loc['stock']->id,
                    'destination_location_id' => $loc['customer']->id,
                    'product_id' => $line->product_id,
                    'quantity' => $line->quantity,
                    'related_sales_order_id' => $order->id,
                    'reserved_at' => in_array($status, [InventoryStockMove::STATUS_RESERVED, InventoryStockMove::STATUS_DONE], true) ? $this->now->subDays(4) : null,
                    'reserved_by' => in_array($status, [InventoryStockMove::STATUS_RESERVED, InventoryStockMove::STATUS_DONE], true) ? $clerkId : null,
                    'completed_at' => $status === InventoryStockMove::STATUS_DONE ? $this->now->subDays(2) : null,
                    'completed_by' => $status === InventoryStockMove::STATUS_DONE ? $clerkId : null,
                    'cancelled_at' => $status === InventoryStockMove::STATUS_CANCELLED ? $this->now->subDay() : null,
                    'cancelled_by' => $status === InventoryStockMove::STATUS_CANCELLED ? $inventoryId : null,
                    'notes' => 'Seeded delivery move.',
                    'created_by' => $clerkId,
                    'updated_by' => $inventoryId,
                ]
            );
        }

        foreach ($purchaseOrders as $order) {
            $line = PurchaseOrderLine::query()->where('order_id', $order->id)->orderBy('created_at')->first();
            if (! $line) {
                continue;
            }
            $status = match ($order->status) {
                PurchaseOrder::STATUS_ORDERED => InventoryStockMove::STATUS_RESERVED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED => InventoryStockMove::STATUS_DONE,
                PurchaseOrder::STATUS_CANCELLED => InventoryStockMove::STATUS_CANCELLED,
                default => InventoryStockMove::STATUS_DRAFT,
            };
            $moves[] = InventoryStockMove::query()->updateOrCreate(
                ['company_id' => $company->id, 'reference' => 'REC-'.$order->order_number],
                [
                    'move_type' => InventoryStockMove::TYPE_RECEIPT,
                    'status' => $status,
                    'source_location_id' => $loc['vendor']->id,
                    'destination_location_id' => $status === InventoryStockMove::STATUS_DONE ? $loc['stock']->id : $loc['input']->id,
                    'product_id' => $line->product_id,
                    'quantity' => $line->received_quantity > 0 ? $line->received_quantity : $line->quantity,
                    'reserved_at' => in_array($status, [InventoryStockMove::STATUS_RESERVED, InventoryStockMove::STATUS_DONE], true) ? $this->now->subDays(5) : null,
                    'reserved_by' => in_array($status, [InventoryStockMove::STATUS_RESERVED, InventoryStockMove::STATUS_DONE], true) ? $clerkId : null,
                    'completed_at' => $status === InventoryStockMove::STATUS_DONE ? $this->now->subDays(3) : null,
                    'completed_by' => $status === InventoryStockMove::STATUS_DONE ? $clerkId : null,
                    'cancelled_at' => $status === InventoryStockMove::STATUS_CANCELLED ? $this->now->subDay() : null,
                    'cancelled_by' => $status === InventoryStockMove::STATUS_CANCELLED ? $inventoryId : null,
                    'notes' => 'Seeded receipt move.',
                    'created_by' => $clerkId,
                    'updated_by' => $inventoryId,
                ]
            );
        }

        for ($i = 1; $i <= 5; $i++) {
            $moves[] = InventoryStockMove::query()->updateOrCreate(
                ['company_id' => $company->id, 'reference' => sprintf('TRN-2026-%03d', $i)],
                [
                    'move_type' => InventoryStockMove::TYPE_TRANSFER,
                    'status' => $i <= 3 ? InventoryStockMove::STATUS_DONE : InventoryStockMove::STATUS_DRAFT,
                    'source_location_id' => $loc['stock']->id,
                    'destination_location_id' => $loc['reserve']->id,
                    'product_id' => $stockProducts[($i - 1) % count($stockProducts)]->id,
                    'quantity' => 5 + ($i * 2),
                    'completed_at' => $i <= 3 ? $this->now->subDays(2) : null,
                    'completed_by' => $i <= 3 ? $clerkId : null,
                    'notes' => 'Seeded transfer move.',
                    'created_by' => $clerkId,
                    'updated_by' => $inventoryId,
                ]
            );

            $moves[] = InventoryStockMove::query()->updateOrCreate(
                ['company_id' => $company->id, 'reference' => sprintf('ADJ-2026-%03d', $i)],
                [
                    'move_type' => InventoryStockMove::TYPE_ADJUSTMENT,
                    'status' => $i <= 4 ? InventoryStockMove::STATUS_DONE : InventoryStockMove::STATUS_DRAFT,
                    'source_location_id' => null,
                    'destination_location_id' => $loc['stock']->id,
                    'product_id' => $stockProducts[($i + 5) % count($stockProducts)]->id,
                    'quantity' => 2 + $i,
                    'completed_at' => $i <= 4 ? $this->now->subDay() : null,
                    'completed_by' => $i <= 4 ? $inventoryId : null,
                    'notes' => 'Seeded adjustment move.',
                    'created_by' => $inventoryId,
                    'updated_by' => $inventoryId,
                ]
            );
        }

        return ['warehouses' => [$mainWarehouse, $reserveWarehouse], 'locations' => array_values($loc), 'moves' => $moves];
    }

    /**
     * @param  array<string, User>  $usersByRole
     * @param  array<int, SalesOrder>  $salesOrders
     * @param  array<int, PurchaseOrder>  $purchaseOrders
     * @return array{
     *   invoices: array<int, AccountingInvoice>,
     *   payments: array<int, AccountingPayment>
     * }
     */
    private function seedAccounting(
        Company $company,
        array $usersByRole,
        array $salesOrders,
        array $purchaseOrders
    ): array {
        $accountantId = $usersByRole['accountant']->id;
        $financeId = $usersByRole['finance_manager']->id;

        $invoices = [];
        $payments = [];

        foreach ($salesOrders as $idx => $order) {
            $status = match ($order->status) {
                SalesOrder::STATUS_CANCELLED => AccountingInvoice::STATUS_CANCELLED,
                SalesOrder::STATUS_DRAFT => AccountingInvoice::STATUS_DRAFT,
                SalesOrder::STATUS_CONFIRMED => AccountingInvoice::STATUS_POSTED,
                SalesOrder::STATUS_FULFILLED => AccountingInvoice::STATUS_PARTIALLY_PAID,
                default => AccountingInvoice::STATUS_PAID,
            };

            $grand = (float) $order->grand_total;
            $paid = match ($status) {
                AccountingInvoice::STATUS_PAID => $grand,
                AccountingInvoice::STATUS_PARTIALLY_PAID => round($grand * 0.58, 2),
                default => 0.0,
            };

            $invoice = AccountingInvoice::query()->updateOrCreate(
                ['company_id' => $company->id, 'invoice_number' => sprintf('AR-INV-2026-%04d', $idx + 1)],
                [
                    'partner_id' => $order->partner_id,
                    'sales_order_id' => $order->id,
                    'purchase_order_id' => null,
                    'document_type' => AccountingInvoice::TYPE_CUSTOMER_INVOICE,
                    'status' => $status,
                    'delivery_status' => $status === AccountingInvoice::STATUS_DRAFT
                        ? (($idx + 1) % 2 === 0 ? AccountingInvoice::DELIVERY_STATUS_READY : AccountingInvoice::DELIVERY_STATUS_PENDING)
                        : AccountingInvoice::DELIVERY_STATUS_NOT_REQUIRED,
                    'invoice_date' => $this->now->subDays(20 - min(18, $idx + 1))->toDateString(),
                    'due_date' => $this->now->addDays(20)->toDateString(),
                    'currency_code' => $company->currency_code,
                    'subtotal' => $order->subtotal,
                    'tax_total' => $order->tax_total,
                    'grand_total' => $grand,
                    'paid_total' => $paid,
                    'balance_due' => max(0, round($grand - $paid, 2)),
                    'posted_by' => in_array($status, [AccountingInvoice::STATUS_POSTED, AccountingInvoice::STATUS_PARTIALLY_PAID, AccountingInvoice::STATUS_PAID], true) ? $accountantId : null,
                    'posted_at' => in_array($status, [AccountingInvoice::STATUS_POSTED, AccountingInvoice::STATUS_PARTIALLY_PAID, AccountingInvoice::STATUS_PAID], true) ? $this->now->subDays(2) : null,
                    'cancelled_by' => $status === AccountingInvoice::STATUS_CANCELLED ? $financeId : null,
                    'cancelled_at' => $status === AccountingInvoice::STATUS_CANCELLED ? $this->now->subDay() : null,
                    'notes' => 'Seeded customer invoice.',
                    'created_by' => $accountantId,
                    'updated_by' => $financeId,
                ]
            );

            AccountingInvoiceLine::query()->where('invoice_id', $invoice->id)->delete();
            foreach (SalesOrderLine::query()->where('order_id', $order->id)->get() as $line) {
                AccountingInvoiceLine::query()->create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate,
                    'line_subtotal' => $line->line_subtotal,
                    'line_total' => $line->line_total,
                    'created_by' => $accountantId,
                    'updated_by' => $financeId,
                ]);
            }

            $invoices[] = $invoice;
        }

        foreach ($purchaseOrders as $idx => $order) {
            $status = match ($order->status) {
                PurchaseOrder::STATUS_CANCELLED => AccountingInvoice::STATUS_CANCELLED,
                PurchaseOrder::STATUS_CLOSED => AccountingInvoice::STATUS_PAID,
                PurchaseOrder::STATUS_BILLED => AccountingInvoice::STATUS_PARTIALLY_PAID,
                PurchaseOrder::STATUS_RECEIVED => AccountingInvoice::STATUS_POSTED,
                default => AccountingInvoice::STATUS_DRAFT,
            };

            $grand = (float) $order->grand_total;
            $paid = match ($status) {
                AccountingInvoice::STATUS_PAID => $grand,
                AccountingInvoice::STATUS_PARTIALLY_PAID => round($grand * 0.5, 2),
                default => 0.0,
            };

            $invoice = AccountingInvoice::query()->updateOrCreate(
                ['company_id' => $company->id, 'invoice_number' => sprintf('AP-BILL-2026-%04d', $idx + 1)],
                [
                    'partner_id' => $order->partner_id,
                    'sales_order_id' => null,
                    'purchase_order_id' => $order->id,
                    'document_type' => AccountingInvoice::TYPE_VENDOR_BILL,
                    'status' => $status,
                    'delivery_status' => AccountingInvoice::DELIVERY_STATUS_NOT_REQUIRED,
                    'invoice_date' => $this->now->subDays(18 - min(16, $idx + 1))->toDateString(),
                    'due_date' => $this->now->addDays(15)->toDateString(),
                    'currency_code' => $company->currency_code,
                    'subtotal' => $order->subtotal,
                    'tax_total' => $order->tax_total,
                    'grand_total' => $grand,
                    'paid_total' => $paid,
                    'balance_due' => max(0, round($grand - $paid, 2)),
                    'posted_by' => in_array($status, [AccountingInvoice::STATUS_POSTED, AccountingInvoice::STATUS_PARTIALLY_PAID, AccountingInvoice::STATUS_PAID], true) ? $accountantId : null,
                    'posted_at' => in_array($status, [AccountingInvoice::STATUS_POSTED, AccountingInvoice::STATUS_PARTIALLY_PAID, AccountingInvoice::STATUS_PAID], true) ? $this->now->subDays(2) : null,
                    'cancelled_by' => $status === AccountingInvoice::STATUS_CANCELLED ? $financeId : null,
                    'cancelled_at' => $status === AccountingInvoice::STATUS_CANCELLED ? $this->now->subDay() : null,
                    'notes' => 'Seeded vendor bill.',
                    'created_by' => $accountantId,
                    'updated_by' => $financeId,
                ]
            );

            AccountingInvoiceLine::query()->where('invoice_id', $invoice->id)->delete();
            foreach (PurchaseOrderLine::query()->where('order_id', $order->id)->get() as $line) {
                AccountingInvoiceLine::query()->create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_cost,
                    'tax_rate' => $line->tax_rate,
                    'line_subtotal' => $line->line_subtotal,
                    'line_total' => $line->line_total,
                    'created_by' => $accountantId,
                    'updated_by' => $financeId,
                ]);
            }

            $invoices[] = $invoice;
        }

        $paymentNo = 1;
        foreach ($invoices as $invoice) {
            if (! in_array($invoice->status, [AccountingInvoice::STATUS_PARTIALLY_PAID, AccountingInvoice::STATUS_PAID], true)) {
                continue;
            }
            $amount = (float) ($invoice->paid_total ?: 0);
            if ($amount <= 0) {
                continue;
            }
            $status = $invoice->status === AccountingInvoice::STATUS_PAID ? AccountingPayment::STATUS_RECONCILED : AccountingPayment::STATUS_POSTED;
            if ($paymentNo % 11 === 0) {
                $status = AccountingPayment::STATUS_REVERSED;
            }

            $payment = AccountingPayment::query()->updateOrCreate(
                ['company_id' => $company->id, 'payment_number' => sprintf('PAY-2026-%04d', $paymentNo)],
                [
                    'invoice_id' => $invoice->id,
                    'status' => $status,
                    'payment_date' => $this->now->subDays(10 - min(8, $paymentNo))->toDateString(),
                    'amount' => $amount,
                    'method' => $paymentNo % 2 === 0 ? 'bank_transfer' : 'card',
                    'reference' => 'PMT-REF-'.$paymentNo,
                    'posted_by' => in_array($status, [AccountingPayment::STATUS_POSTED, AccountingPayment::STATUS_RECONCILED, AccountingPayment::STATUS_REVERSED], true) ? $accountantId : null,
                    'posted_at' => in_array($status, [AccountingPayment::STATUS_POSTED, AccountingPayment::STATUS_RECONCILED, AccountingPayment::STATUS_REVERSED], true) ? $this->now->subDays(2) : null,
                    'reconciled_at' => $status === AccountingPayment::STATUS_RECONCILED ? $this->now->subDay() : null,
                    'reversed_by' => $status === AccountingPayment::STATUS_REVERSED ? $financeId : null,
                    'reversed_at' => $status === AccountingPayment::STATUS_REVERSED ? $this->now->subHours(18) : null,
                    'reversal_reason' => $status === AccountingPayment::STATUS_REVERSED ? 'Seeded reversal.' : null,
                    'notes' => 'Seeded payment.',
                    'created_by' => $accountantId,
                    'updated_by' => $financeId,
                ]
            );

            if ($status === AccountingPayment::STATUS_RECONCILED) {
                AccountingReconciliationEntry::query()->create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'entry_type' => AccountingReconciliationEntry::TYPE_APPLY,
                    'amount' => $amount,
                    'reconciled_at' => $this->now->subDay(),
                    'created_by' => $accountantId,
                    'updated_by' => $accountantId,
                ]);
            }
            if ($status === AccountingPayment::STATUS_REVERSED) {
                AccountingReconciliationEntry::query()->create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'entry_type' => AccountingReconciliationEntry::TYPE_REVERSAL,
                    'amount' => $amount,
                    'reconciled_at' => $this->now->subHours(12),
                    'created_by' => $financeId,
                    'updated_by' => $financeId,
                ]);
            }

            $payments[] = $payment;
            $paymentNo++;
        }

        return ['invoices' => $invoices, 'payments' => $payments];
    }

    /**
     * @param  array<string, User>  $usersByRole
     * @param  array<string, Role>  $rolesBySlug
     * @param  array<int, SalesQuote>  $salesQuotes
     * @param  array<int, SalesOrder>  $salesOrders
     * @param  array<int, PurchaseOrder>  $purchaseOrders
     */
    private function seedApprovals(
        Company $company,
        array $usersByRole,
        array $rolesBySlug,
        array $salesQuotes,
        array $salesOrders,
        array $purchaseOrders
    ): void {
        $ownerId = $usersByRole['owner']->id;
        $approverId = $usersByRole['approver']->id;
        $financeId = $usersByRole['finance_manager']->id;

        $profiles = [
            [ApprovalRequest::MODULE_SALES, ApprovalRequest::ACTION_SALES_QUOTE_APPROVAL, $approverId, null, 25000, ApprovalRequest::RISK_HIGH],
            [ApprovalRequest::MODULE_SALES, ApprovalRequest::ACTION_SALES_ORDER_APPROVAL, $approverId, null, 30000, ApprovalRequest::RISK_HIGH],
            [ApprovalRequest::MODULE_PURCHASING, ApprovalRequest::ACTION_PURCHASE_ORDER_APPROVAL, $financeId, null, 50000, ApprovalRequest::RISK_CRITICAL],
            [ApprovalRequest::MODULE_PURCHASING, ApprovalRequest::ACTION_PURCHASE_ORDER_APPROVAL, null, $rolesBySlug['purchasing_manager']->id, 12000, ApprovalRequest::RISK_MEDIUM],
        ];

        foreach ($profiles as [$module, $action, $userId, $roleId, $maxAmount, $maxRisk]) {
            ApprovalAuthorityProfile::query()->updateOrCreate(
                ['company_id' => $company->id, 'module' => $module, 'action' => $action, 'user_id' => $userId, 'role_id' => $roleId],
                [
                    'max_amount' => $maxAmount,
                    'currency_code' => $company->currency_code,
                    'max_risk_level' => $maxRisk,
                    'requires_separate_requester' => true,
                    'is_active' => true,
                    'created_by' => $ownerId,
                    'updated_by' => $ownerId,
                ]
            );
        }

        foreach ($salesQuotes as $quote) {
            if (! $quote->requires_approval) {
                continue;
            }
            $status = match ($quote->status) {
                SalesQuote::STATUS_REJECTED => ApprovalRequest::STATUS_REJECTED,
                SalesQuote::STATUS_APPROVED, SalesQuote::STATUS_CONFIRMED => ApprovalRequest::STATUS_APPROVED,
                default => ApprovalRequest::STATUS_PENDING,
            };
            $request = ApprovalRequest::query()->updateOrCreate(
                ['company_id' => $company->id, 'source_type' => SalesQuote::class, 'source_id' => $quote->id],
                [
                    'module' => ApprovalRequest::MODULE_SALES,
                    'action' => ApprovalRequest::ACTION_SALES_QUOTE_APPROVAL,
                    'source_number' => $quote->quote_number,
                    'status' => $status,
                    'requested_by_user_id' => $quote->created_by,
                    'requested_at' => $this->now->subDays(6),
                    'amount' => $quote->grand_total,
                    'currency_code' => $company->currency_code,
                    'risk_level' => $this->riskLevelForAmount((float) $quote->grand_total),
                    'approved_by_user_id' => $status === ApprovalRequest::STATUS_APPROVED ? $approverId : null,
                    'approved_at' => $status === ApprovalRequest::STATUS_APPROVED ? $this->now->subDays(5) : null,
                    'rejected_by_user_id' => $status === ApprovalRequest::STATUS_REJECTED ? $approverId : null,
                    'rejected_at' => $status === ApprovalRequest::STATUS_REJECTED ? $this->now->subDays(5) : null,
                    'rejection_reason' => $status === ApprovalRequest::STATUS_REJECTED ? 'Seeded rejection path.' : null,
                    'metadata' => ['seed' => 'demo_workflow', 'source' => 'sales.quote'],
                    'created_by' => $quote->created_by,
                    'updated_by' => $usersByRole['sales_manager']->id,
                ]
            );
            $this->seedApprovalSteps($company, $request, $status, $approverId, $financeId);
        }

        foreach ($salesOrders as $order) {
            if (! $order->requires_approval) {
                continue;
            }
            $status = match ($order->status) {
                SalesOrder::STATUS_CANCELLED => ApprovalRequest::STATUS_REJECTED,
                SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_FULFILLED, SalesOrder::STATUS_INVOICED, SalesOrder::STATUS_CLOSED => ApprovalRequest::STATUS_APPROVED,
                default => ApprovalRequest::STATUS_PENDING,
            };
            $request = ApprovalRequest::query()->updateOrCreate(
                ['company_id' => $company->id, 'source_type' => SalesOrder::class, 'source_id' => $order->id],
                [
                    'module' => ApprovalRequest::MODULE_SALES,
                    'action' => ApprovalRequest::ACTION_SALES_ORDER_APPROVAL,
                    'source_number' => $order->order_number,
                    'status' => $status,
                    'requested_by_user_id' => $order->created_by,
                    'requested_at' => $this->now->subDays(4),
                    'amount' => $order->grand_total,
                    'currency_code' => $company->currency_code,
                    'risk_level' => $this->riskLevelForAmount((float) $order->grand_total),
                    'approved_by_user_id' => $status === ApprovalRequest::STATUS_APPROVED ? $approverId : null,
                    'approved_at' => $status === ApprovalRequest::STATUS_APPROVED ? $this->now->subDays(3) : null,
                    'rejected_by_user_id' => $status === ApprovalRequest::STATUS_REJECTED ? $approverId : null,
                    'rejected_at' => $status === ApprovalRequest::STATUS_REJECTED ? $this->now->subDays(3) : null,
                    'rejection_reason' => $status === ApprovalRequest::STATUS_REJECTED ? 'Seeded cancelled-order rejection.' : null,
                    'metadata' => ['seed' => 'demo_workflow', 'source' => 'sales.order'],
                    'created_by' => $order->created_by,
                    'updated_by' => $usersByRole['sales_manager']->id,
                ]
            );
            $this->seedApprovalSteps($company, $request, $status, $approverId, $financeId);
        }

        foreach ($purchaseOrders as $order) {
            if (! $order->requires_approval) {
                continue;
            }
            $status = match ($order->status) {
                PurchaseOrder::STATUS_CANCELLED => ApprovalRequest::STATUS_REJECTED,
                PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_BILLED, PurchaseOrder::STATUS_CLOSED => ApprovalRequest::STATUS_APPROVED,
                default => ApprovalRequest::STATUS_PENDING,
            };
            $request = ApprovalRequest::query()->updateOrCreate(
                ['company_id' => $company->id, 'source_type' => PurchaseOrder::class, 'source_id' => $order->id],
                [
                    'module' => ApprovalRequest::MODULE_PURCHASING,
                    'action' => ApprovalRequest::ACTION_PURCHASE_ORDER_APPROVAL,
                    'source_number' => $order->order_number,
                    'status' => $status,
                    'requested_by_user_id' => $order->created_by,
                    'requested_at' => $this->now->subDays(4),
                    'amount' => $order->grand_total,
                    'currency_code' => $company->currency_code,
                    'risk_level' => $this->riskLevelForAmount((float) $order->grand_total),
                    'approved_by_user_id' => $status === ApprovalRequest::STATUS_APPROVED ? $financeId : null,
                    'approved_at' => $status === ApprovalRequest::STATUS_APPROVED ? $this->now->subDays(3) : null,
                    'rejected_by_user_id' => $status === ApprovalRequest::STATUS_REJECTED ? $financeId : null,
                    'rejected_at' => $status === ApprovalRequest::STATUS_REJECTED ? $this->now->subDays(3) : null,
                    'rejection_reason' => $status === ApprovalRequest::STATUS_REJECTED ? 'Seeded cancelled purchase-order rejection.' : null,
                    'metadata' => ['seed' => 'demo_workflow', 'source' => 'purchasing.order'],
                    'created_by' => $order->created_by,
                    'updated_by' => $usersByRole['purchasing_manager']->id,
                ]
            );
            $this->seedApprovalSteps($company, $request, $status, $financeId, $approverId);
        }
    }

    private function seedApprovalSteps(Company $company, ApprovalRequest $request, string $status, string $primaryApproverId, string $secondaryApproverId): void
    {
        ApprovalStep::query()->where('approval_request_id', $request->id)->delete();

        $stepOneStatus = match ($status) {
            ApprovalRequest::STATUS_APPROVED => ApprovalStep::STATUS_APPROVED,
            ApprovalRequest::STATUS_REJECTED => ApprovalStep::STATUS_REJECTED,
            ApprovalRequest::STATUS_CANCELLED => ApprovalStep::STATUS_SKIPPED,
            default => ApprovalStep::STATUS_PENDING,
        };

        ApprovalStep::query()->create([
            'company_id' => $company->id,
            'approval_request_id' => $request->id,
            'step_order' => 1,
            'approver_user_id' => $primaryApproverId,
            'status' => $stepOneStatus,
            'decision_notes' => $stepOneStatus === ApprovalStep::STATUS_APPROVED ? 'Seeded automatic approval.' : ($stepOneStatus === ApprovalStep::STATUS_REJECTED ? 'Seeded rejection trail.' : null),
            'acted_at' => in_array($stepOneStatus, [ApprovalStep::STATUS_APPROVED, ApprovalStep::STATUS_REJECTED], true) ? $this->now->subDays(2) : null,
            'created_by' => $primaryApproverId,
            'updated_by' => $primaryApproverId,
        ]);

        if ((float) ($request->amount ?? 0) < 10000) {
            return;
        }

        $stepTwoStatus = match ($status) {
            ApprovalRequest::STATUS_APPROVED => ApprovalStep::STATUS_APPROVED,
            ApprovalRequest::STATUS_REJECTED, ApprovalRequest::STATUS_CANCELLED => ApprovalStep::STATUS_SKIPPED,
            default => ApprovalStep::STATUS_PENDING,
        };

        ApprovalStep::query()->create([
            'company_id' => $company->id,
            'approval_request_id' => $request->id,
            'step_order' => 2,
            'approver_user_id' => $secondaryApproverId,
            'status' => $stepTwoStatus,
            'decision_notes' => $stepTwoStatus === ApprovalStep::STATUS_APPROVED ? 'Seeded secondary approval.' : null,
            'acted_at' => $stepTwoStatus === ApprovalStep::STATUS_APPROVED ? $this->now->subDay() : null,
            'created_by' => $secondaryApproverId,
            'updated_by' => $secondaryApproverId,
        ]);
    }

    /**
     * @param  array<string, User>  $usersByRole
     */
    private function seedNotifications(array $usersByRole): void
    {
        $users = array_values($usersByRole);
        $severities = ['low', 'medium', 'high', 'critical'];
        $events = [
            ['title' => 'Sales order confirmed', 'url' => '/company/sales/orders', 'source' => 'sales'],
            ['title' => 'Purchase receipt posted', 'url' => '/company/purchasing/orders', 'source' => 'purchasing'],
            ['title' => 'Invoice posted', 'url' => '/company/accounting/invoices', 'source' => 'accounting'],
            ['title' => 'Stock move completed', 'url' => '/company/inventory/moves', 'source' => 'inventory'],
            ['title' => 'Approval queued', 'url' => '/company/approvals', 'source' => 'approvals'],
        ];

        $rows = [];
        $counter = 1;
        foreach ($users as $user) {
            for ($i = 0; $i < 4; $i++) {
                $event = $events[($counter + $i) % count($events)];
                $severity = $severities[($counter + $i) % count($severities)];
                $createdAt = $this->now->subDays(($counter + $i) % 14)->subMinutes(($counter * 13) % 480);

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\DemoWorkflowNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'title' => $event['title'],
                        'message' => 'Seeded notification for demo walkthrough.',
                        'url' => $event['url'],
                        'severity' => $severity,
                        'meta' => ['source' => $event['source'], 'event' => $event['title']],
                    ]),
                    'read_at' => $i === 0 ? $createdAt->addHours(4) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                $counter++;
            }
        }

        DB::table('notifications')->insert($rows);
    }

    /**
     * @param  array<string, User>  $usersByRole
     * @param  array{
     *   leads: array<int, SalesLead>,
     *   quotes: array<int, SalesQuote>,
     *   orders: array<int, SalesOrder>
     * }  $sales
     * @param  array{
     *   rfqs: array<int, PurchaseRfq>,
     *   orders: array<int, PurchaseOrder>
     * }  $purchasing
     * @param  array{
     *   invoices: array<int, AccountingInvoice>,
     *   payments: array<int, AccountingPayment>
     * }  $accounting
     * @param  array{
     *   moves: array<int, InventoryStockMove>
     * }  $inventory
     * @param  array<int, Invite>  $invites
     */
    private function seedAuditLogs(
        Company $company,
        array $usersByRole,
        array $sales,
        array $purchasing,
        array $accounting,
        array $inventory,
        array $invites
    ): void {
        $actors = array_values($usersByRole);
        $targets = array_merge(
            $sales['leads'],
            $sales['quotes'],
            $sales['orders'],
            $purchasing['rfqs'],
            $purchasing['orders'],
            $accounting['invoices'],
            $accounting['payments'],
            $inventory['moves'],
            $invites
        );

        if ($targets === []) {
            return;
        }

        $actions = ['created', 'updated', 'approved', 'reconciled', 'exported'];
        for ($i = 1; $i <= 120; $i++) {
            $target = $targets[($i - 1) % count($targets)];
            $actor = $actors[($i - 1) % count($actors)];
            AuditLog::query()->create([
                'company_id' => $company->id,
                'user_id' => $actor->id,
                'auditable_type' => $target::class,
                'auditable_id' => (string) $target->id,
                'action' => $actions[($i - 1) % count($actions)],
                'changes' => ['seeded_event' => true, 'sequence' => $i],
                'metadata' => ['source' => 'demo-seeder', 'channel' => 'console'],
                'created_at' => $this->now->subDays(($i - 1) % 35)->subMinutes(($i * 19) % 720),
            ]);
        }
    }

    private function riskLevelForAmount(float $amount): string
    {
        return match (true) {
            $amount < 5000 => ApprovalRequest::RISK_LOW,
            $amount < 12000 => ApprovalRequest::RISK_MEDIUM,
            $amount < 25000 => ApprovalRequest::RISK_HIGH,
            default => ApprovalRequest::RISK_CRITICAL,
        };
    }
}
