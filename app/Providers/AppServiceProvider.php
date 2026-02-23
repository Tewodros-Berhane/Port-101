<?php

namespace App\Providers;

use App\Modules\Accounting\AccountingInvoiceWorkflowService;
use App\Modules\Inventory\Events\StockDelivered;
use App\Modules\Inventory\InventoryStockWorkflowService;
use App\Modules\Purchasing\Events\PurchaseReceiptCompleted;
use App\Modules\Sales\Events\SalesOrderConfirmed;
use App\Modules\Sales\Events\SalesOrderReadyForInvoice;
use App\Core\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CompanyContext::class, function () {
            return new CompanyContext();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerDomainListeners();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function registerDomainListeners(): void
    {
        Event::listen(SalesOrderConfirmed::class, function (SalesOrderConfirmed $event): void {
            app(InventoryStockWorkflowService::class)
                ->reserveSalesOrder(
                    companyId: $event->companyId,
                    orderId: $event->orderId,
                );
        });

        Event::listen(SalesOrderReadyForInvoice::class, function (SalesOrderReadyForInvoice $event): void {
            app(AccountingInvoiceWorkflowService::class)
                ->createOrRefreshFromSalesOrder(
                    companyId: $event->companyId,
                    orderId: $event->orderId,
                );
        });

        Event::listen(StockDelivered::class, function (StockDelivered $event): void {
            app(AccountingInvoiceWorkflowService::class)
                ->markDeliveryReadyForSalesOrder(
                    companyId: $event->companyId,
                    orderId: $event->orderId,
                );
        });

        Event::listen(PurchaseReceiptCompleted::class, function (PurchaseReceiptCompleted $event): void {
            app(AccountingInvoiceWorkflowService::class)
                ->createOrRefreshVendorBillFromPurchaseOrder(
                    companyId: $event->companyId,
                    orderId: $event->orderId,
                );
        });
    }
}


