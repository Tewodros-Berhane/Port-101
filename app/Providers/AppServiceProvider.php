<?php

namespace App\Providers;

use App\Core\Support\CompanyContext;
use App\Modules\Accounting\AccountingInvoiceWorkflowService;
use App\Modules\Integrations\OutboundEventService;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Inventory\Events\StockDelivered;
use App\Modules\Inventory\InventoryStockWorkflowService;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Projects\ProjectSalesProvisioningService;
use App\Modules\Purchasing\Events\PurchaseReceiptCompleted;
use App\Modules\Sales\Events\SalesOrderConfirmed;
use App\Modules\Sales\Events\SalesOrderReadyForInvoice;
use App\Modules\Sales\Models\SalesOrder;
use App\Support\Logging\StructuredLogContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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
            return new CompanyContext;
        });

        $this->app->singleton(StructuredLogContext::class, function () {
            return new StructuredLogContext;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerStructuredLogging();
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

    protected function registerStructuredLogging(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            app(StructuredLogContext::class)->setConsoleContext($event->command);
        });

        Event::listen(CommandFinished::class, function (): void {
            app(StructuredLogContext::class)->clearScope('runtime');
        });

        Queue::before(function (JobProcessing $event): void {
            $context = $this->queueContext($event->job);

            app(StructuredLogContext::class)->setQueueContext($context);

            Log::info('Queue job processing started.', [
                ...$context,
                'module' => $context['module'] ?? 'queue',
                'entity' => $context['entity'] ?? 'job',
                'action' => 'processing_started',
            ]);
        });

        Queue::after(function (JobProcessed $event): void {
            $context = $this->queueContext($event->job);

            Log::info('Queue job processed successfully.', [
                ...$context,
                'module' => $context['module'] ?? 'queue',
                'entity' => $context['entity'] ?? 'job',
                'action' => 'processed',
            ]);

            app(StructuredLogContext::class)->clearScope('queue');
        });

        Queue::failing(function (JobFailed $event): void {
            $context = $this->queueContext($event->job);

            Log::error('Queue job failed.', [
                ...$context,
                'module' => $context['module'] ?? 'queue',
                'entity' => $context['entity'] ?? 'job',
                'action' => 'failed',
                'exception' => $event->exception::class,
                'exception_message' => $event->exception->getMessage(),
            ]);

            app(StructuredLogContext::class)->clearScope('queue');
        });
    }

    protected function registerDomainListeners(): void
    {
        Event::listen(SalesOrderConfirmed::class, function (SalesOrderConfirmed $event): void {
            app(InventoryStockWorkflowService::class)
                ->reserveSalesOrder(
                    companyId: $event->companyId,
                    orderId: $event->orderId,
                );

            app(ProjectSalesProvisioningService::class)
                ->createOrRefreshFromSalesOrder(
                    companyId: $event->companyId,
                    orderId: $event->orderId,
                );

            $order = SalesOrder::query()
                ->with(['partner:id,name', 'lines.product:id,name,sku,type'])
                ->where('company_id', $event->companyId)
                ->find($event->orderId);

            if (! $order) {
                return;
            }

            app(OutboundEventService::class)->record(
                companyId: (string) $order->company_id,
                eventType: WebhookEventCatalog::SALES_ORDER_CONFIRMED,
                aggregateType: SalesOrder::class,
                aggregateId: (string) $order->id,
                data: [
                    'object_type' => 'sales_order',
                    'object_id' => (string) $order->id,
                    'order_number' => (string) $order->order_number,
                    'status' => (string) $order->status,
                    'quote_id' => $order->quote_id ? (string) $order->quote_id : null,
                    'customer_id' => $order->partner_id ? (string) $order->partner_id : null,
                    'customer_name' => $order->partner?->name,
                    'order_date' => $order->order_date?->toDateString(),
                    'grand_total' => (float) $order->grand_total,
                    'line_count' => $order->lines->count(),
                    'lines' => $order->lines
                        ->map(fn ($line) => [
                            'product_id' => $line->product_id ? (string) $line->product_id : null,
                            'product_name' => $line->product?->name,
                            'product_type' => $line->product?->type,
                            'sku' => $line->product?->sku,
                            'description' => $line->description,
                            'quantity' => (float) $line->quantity,
                            'line_total' => (float) $line->line_total,
                        ])
                        ->values()
                        ->all(),
                ],
                actorId: $order->confirmed_by ? (string) $order->confirmed_by : null,
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

            $move = InventoryStockMove::query()
                ->with([
                    'product:id,name,sku,type',
                    'salesOrder:id,order_number,partner_id',
                    'salesOrder.partner:id,name',
                    'sourceLocation:id,name,code',
                    'destinationLocation:id,name,code',
                ])
                ->where('company_id', $event->companyId)
                ->find($event->moveId);

            if (! $move) {
                return;
            }

            app(OutboundEventService::class)->record(
                companyId: (string) $move->company_id,
                eventType: WebhookEventCatalog::INVENTORY_DELIVERY_COMPLETED,
                aggregateType: InventoryStockMove::class,
                aggregateId: (string) $move->id,
                data: [
                    'object_type' => 'inventory_stock_move',
                    'object_id' => (string) $move->id,
                    'reference' => (string) $move->reference,
                    'status' => (string) $move->status,
                    'move_type' => (string) $move->move_type,
                    'product_id' => $move->product_id ? (string) $move->product_id : null,
                    'product_name' => $move->product?->name,
                    'sku' => $move->product?->sku,
                    'quantity' => (float) $move->quantity,
                    'sales_order_id' => $move->related_sales_order_id ? (string) $move->related_sales_order_id : null,
                    'sales_order_number' => $move->salesOrder?->order_number,
                    'customer_id' => $move->salesOrder?->partner_id ? (string) $move->salesOrder?->partner_id : null,
                    'customer_name' => $move->salesOrder?->partner?->name,
                    'source_location' => $move->sourceLocation?->name,
                    'source_location_code' => $move->sourceLocation?->code,
                    'destination_location' => $move->destinationLocation?->name,
                    'destination_location_code' => $move->destinationLocation?->code,
                    'completed_at' => $move->completed_at?->toIso8601String(),
                ],
                actorId: $move->completed_by ? (string) $move->completed_by : null,
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

    /**
     * @return array<string, mixed>
     */
    private function queueContext(Job $job): array
    {
        $resolvedName = $job->resolveName();
        $segments = explode('\\', $resolvedName);
        $jobName = end($segments) ?: $resolvedName;

        return [
            'job_name' => $resolvedName,
            'queue_connection' => $job->getConnectionName(),
            'queue_name' => $job->getQueue(),
            'job_id' => method_exists($job, 'getJobId') ? $job->getJobId() : null,
            'module' => 'queue',
            'entity' => $jobName,
            'action' => 'handle',
        ];
    }
}
