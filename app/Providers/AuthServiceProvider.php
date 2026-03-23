<?php

namespace App\Providers;

use App\Core\Attachments\Models\Attachment;
use App\Core\Audit\Models\AuditLog;
use App\Core\Company\Models\Company;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Contact;
use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\PriceList;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Core\RBAC\Models\Permission;
use App\Core\RBAC\Models\Role;
use App\Modules\Accounting\Models\AccountingBankReconciliationBatch;
use App\Modules\Accounting\Models\AccountingInvoice;
use App\Modules\Accounting\Models\AccountingManualJournal;
use App\Modules\Accounting\Models\AccountingPayment;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryLot;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Inventory\Models\InventoryWarehouse;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRfq;
use App\Modules\Reports\Models\ReportExport;
use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Policies\AccountingBankReconciliationPolicy;
use App\Policies\AccountingInvoicePolicy;
use App\Policies\AccountingManualJournalPolicy;
use App\Policies\AccountingPaymentPolicy;
use App\Policies\AddressPolicy;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContactPolicy;
use App\Policies\CurrencyPolicy;
use App\Policies\InventoryLocationPolicy;
use App\Policies\InventoryLotPolicy;
use App\Policies\InventoryStockLevelPolicy;
use App\Policies\InventoryStockMovePolicy;
use App\Policies\InventoryWarehousePolicy;
use App\Policies\PartnerPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProjectBillablePolicy;
use App\Policies\ProjectMilestonePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ProjectRecurringBillingPolicy;
use App\Policies\ProjectTaskPolicy;
use App\Policies\ProjectTimesheetPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRfqPolicy;
use App\Policies\ReportExportPolicy;
use App\Policies\RolePolicy;
use App\Policies\SalesLeadPolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SalesQuotePolicy;
use App\Policies\TaxPolicy;
use App\Policies\UomPolicy;
use App\Policies\WebhookDeliveryPolicy;
use App\Policies\WebhookEndpointPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Company::class => CompanyPolicy::class,
        Attachment::class => AttachmentPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        ApprovalRequest::class => ApprovalRequestPolicy::class,
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        Address::class => AddressPolicy::class,
        Contact::class => ContactPolicy::class,
        Partner::class => PartnerPolicy::class,
        Product::class => ProductPolicy::class,
        Tax::class => TaxPolicy::class,
        Currency::class => CurrencyPolicy::class,
        Uom::class => UomPolicy::class,
        PriceList::class => PriceListPolicy::class,
        SalesLead::class => SalesLeadPolicy::class,
        SalesQuote::class => SalesQuotePolicy::class,
        SalesOrder::class => SalesOrderPolicy::class,
        PurchaseRfq::class => PurchaseRfqPolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        ReportExport::class => ReportExportPolicy::class,
        Project::class => ProjectPolicy::class,
        ProjectTask::class => ProjectTaskPolicy::class,
        ProjectTimesheet::class => ProjectTimesheetPolicy::class,
        ProjectMilestone::class => ProjectMilestonePolicy::class,
        ProjectBillable::class => ProjectBillablePolicy::class,
        ProjectRecurringBilling::class => ProjectRecurringBillingPolicy::class,
        AccountingBankReconciliationBatch::class => AccountingBankReconciliationPolicy::class,
        AccountingInvoice::class => AccountingInvoicePolicy::class,
        AccountingManualJournal::class => AccountingManualJournalPolicy::class,
        AccountingPayment::class => AccountingPaymentPolicy::class,
        InventoryWarehouse::class => InventoryWarehousePolicy::class,
        InventoryLocation::class => InventoryLocationPolicy::class,
        InventoryLot::class => InventoryLotPolicy::class,
        InventoryStockLevel::class => InventoryStockLevelPolicy::class,
        InventoryStockMove::class => InventoryStockMovePolicy::class,
        WebhookEndpoint::class => WebhookEndpointPolicy::class,
        WebhookDelivery::class => WebhookDeliveryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
