<?php

use App\Core\MasterData\Models\Partner;
use App\Core\MasterData\Models\Product;
use App\Modules\Accounting\AccountingInvoiceWorkflowService;
use App\Modules\Accounting\AccountingPaymentWorkflowService;
use App\Modules\Integrations\Models\IntegrationEvent;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Inventory\InventorySetupService;
use App\Modules\Inventory\InventoryStockWorkflowService;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Projects\Models\Project;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\SalesOrderWorkflowService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function createWebhookDeliveryEndpoint(
    string $companyId,
    string $userId,
    array $events,
): WebhookEndpoint {
    return WebhookEndpoint::create([
        'company_id' => $companyId,
        'name' => 'Webhook Delivery Endpoint '.Str::upper(Str::random(4)),
        'target_url' => 'https://hooks.example.com/'.Str::lower(Str::random(8)),
        'signing_secret' => Str::random(64),
        'api_version' => 'v1',
        'is_active' => true,
        'subscribed_events' => $events,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function createWebhookDeliveryPartner(string $companyId, string $userId): Partner
{
    return Partner::create([
        'company_id' => $companyId,
        'code' => 'WHK-'.Str::upper(Str::random(6)),
        'name' => 'Webhook Customer '.Str::upper(Str::random(4)),
        'type' => 'customer',
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

function createWebhookDeliveryProduct(string $companyId, string $userId, string $type): Product
{
    return Product::create([
        'company_id' => $companyId,
        'name' => 'Webhook Product '.Str::upper(Str::random(4)),
        'sku' => 'WHK-SKU-'.Str::upper(Str::random(4)),
        'type' => $type,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('sales order confirmation publishes outbound webhook events', function () {
    Http::fake();

    [$user, $company] = makeActiveCompanyMember();
    $partner = createWebhookDeliveryPartner($company->id, $user->id);
    $product = createWebhookDeliveryProduct($company->id, $user->id, 'stock');
    $endpoint = createWebhookDeliveryEndpoint($company->id, $user->id, [
        WebhookEventCatalog::SALES_ORDER_CONFIRMED,
    ]);

    $order = app(SalesOrderWorkflowService::class)->create([
        'partner_id' => $partner->id,
        'order_date' => now()->toDateString(),
        'lines' => [[
            'product_id' => $product->id,
            'description' => 'Webhook stock order',
            'quantity' => 2,
            'unit_price' => 125,
            'discount_percent' => 0,
            'tax_rate' => 0,
        ]],
    ], $user);

    $confirmedOrder = app(SalesOrderWorkflowService::class)->confirm($order, $user);

    $event = IntegrationEvent::query()
        ->where('company_id', $company->id)
        ->where('event_type', WebhookEventCatalog::SALES_ORDER_CONFIRMED)
        ->latest('created_at')
        ->first();

    expect($event)->not->toBeNull();
    expect(data_get($event?->payload, 'event_id'))->toBe((string) $event?->id);
    expect(data_get($event?->payload, 'data.order_number'))->toBe($confirmedOrder->order_number);
    expect(data_get($event?->payload, 'data.customer_name'))->toBe($partner->name);

    $delivery = WebhookDelivery::query()
        ->where('webhook_endpoint_id', $endpoint->id)
        ->where('integration_event_id', $event?->id)
        ->first();

    expect($delivery)->not->toBeNull();
    expect($delivery?->status)->toBe(WebhookDelivery::STATUS_DELIVERED);
    expect($delivery?->response_status)->toBe(200);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint, $confirmedOrder) {
        $payload = json_decode($request->body(), true);

        return $request->url() === $endpoint->target_url
            && $request->hasHeader('X-Port101-Event', WebhookEventCatalog::SALES_ORDER_CONFIRMED)
            && ($payload['data']['order_number'] ?? null) === $confirmedOrder->order_number
            && ($payload['data']['status'] ?? null) === SalesOrder::STATUS_CONFIRMED;
    });
});

test('service order provisioning publishes project provisioned webhooks', function () {
    Http::fake();

    [$user, $company] = makeActiveCompanyMember();
    $partner = createWebhookDeliveryPartner($company->id, $user->id);
    $product = createWebhookDeliveryProduct($company->id, $user->id, 'service');
    $endpoint = createWebhookDeliveryEndpoint($company->id, $user->id, [
        WebhookEventCatalog::PROJECT_PROVISIONED,
    ]);

    $order = app(SalesOrderWorkflowService::class)->create([
        'partner_id' => $partner->id,
        'order_date' => now()->toDateString(),
        'lines' => [[
            'product_id' => $product->id,
            'description' => 'Webhook service order',
            'quantity' => 4,
            'unit_price' => 200,
            'discount_percent' => 0,
            'tax_rate' => 0,
        ]],
    ], $user);

    app(SalesOrderWorkflowService::class)->confirm($order, $user);

    $project = Project::query()
        ->where('company_id', $company->id)
        ->where('sales_order_id', $order->id)
        ->first();

    expect($project)->not->toBeNull();

    $event = IntegrationEvent::query()
        ->where('company_id', $company->id)
        ->where('event_type', WebhookEventCatalog::PROJECT_PROVISIONED)
        ->latest('created_at')
        ->first();

    expect($event)->not->toBeNull();
    expect(data_get($event?->payload, 'data.object_id'))->toBe((string) $project?->id);
    expect(data_get($event?->payload, 'data.sales_order_number'))->toBe($order->order_number);

    $delivery = WebhookDelivery::query()
        ->where('webhook_endpoint_id', $endpoint->id)
        ->where('integration_event_id', $event?->id)
        ->first();

    expect($delivery)->not->toBeNull();
    expect($delivery?->status)->toBe(WebhookDelivery::STATUS_DELIVERED);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint, $project) {
        $payload = json_decode($request->body(), true);

        return $request->url() === $endpoint->target_url
            && $request->hasHeader('X-Port101-Event', WebhookEventCatalog::PROJECT_PROVISIONED)
            && ($payload['data']['project_code'] ?? null) === $project?->project_code;
    });
});

test('invoice posting publishes accounting invoice posted webhooks', function () {
    Http::fake();

    [$user, $company] = makeActiveCompanyMember();
    $partner = createWebhookDeliveryPartner($company->id, $user->id);
    $product = createWebhookDeliveryProduct($company->id, $user->id, 'service');
    $endpoint = createWebhookDeliveryEndpoint($company->id, $user->id, [
        WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED,
    ]);

    $invoice = app(AccountingInvoiceWorkflowService::class)->createDraft([
        'partner_id' => $partner->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'currency_code' => 'USD',
        'lines' => [[
            'product_id' => $product->id,
            'description' => 'Webhook invoice line',
            'quantity' => 2,
            'unit_price' => 300,
            'tax_rate' => 0,
        ]],
    ], $company->id, $user->id);

    $postedInvoice = app(AccountingInvoiceWorkflowService::class)->post($invoice, $user->id);

    $event = IntegrationEvent::query()
        ->where('company_id', $company->id)
        ->where('event_type', WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED)
        ->latest('created_at')
        ->first();

    expect($event)->not->toBeNull();
    expect(data_get($event?->payload, 'data.invoice_number'))->toBe($postedInvoice->invoice_number);
    expect(data_get($event?->payload, 'data.partner_name'))->toBe($partner->name);

    $delivery = WebhookDelivery::query()
        ->where('webhook_endpoint_id', $endpoint->id)
        ->where('integration_event_id', $event?->id)
        ->first();

    expect($delivery)->not->toBeNull();
    expect($delivery?->status)->toBe(WebhookDelivery::STATUS_DELIVERED);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint, $postedInvoice) {
        $payload = json_decode($request->body(), true);

        return $request->url() === $endpoint->target_url
            && $request->hasHeader('X-Port101-Event', WebhookEventCatalog::ACCOUNTING_INVOICE_POSTED)
            && ($payload['data']['invoice_number'] ?? null) === $postedInvoice->invoice_number
            && ($payload['data']['status'] ?? null) === $postedInvoice->status;
    });
});

test('payment reconciliation publishes accounting payment received webhooks', function () {
    Http::fake();

    [$user, $company] = makeActiveCompanyMember();
    $partner = createWebhookDeliveryPartner($company->id, $user->id);
    $product = createWebhookDeliveryProduct($company->id, $user->id, 'service');
    $endpoint = createWebhookDeliveryEndpoint($company->id, $user->id, [
        WebhookEventCatalog::ACCOUNTING_PAYMENT_RECEIVED,
    ]);

    $invoiceService = app(AccountingInvoiceWorkflowService::class);
    $paymentService = app(AccountingPaymentWorkflowService::class);

    $invoice = $invoiceService->createDraft([
        'partner_id' => $partner->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'currency_code' => 'USD',
        'lines' => [[
            'product_id' => $product->id,
            'description' => 'Webhook payment invoice line',
            'quantity' => 1,
            'unit_price' => 450,
            'tax_rate' => 0,
        ]],
    ], $company->id, $user->id);

    $invoice = $invoiceService->post($invoice, $user->id);

    $payment = $paymentService->createDraft([
        'invoice_id' => $invoice->id,
        'payment_date' => now()->toDateString(),
        'amount' => 450,
        'method' => 'bank_transfer',
        'reference' => 'PMT-REF-001',
    ], $company->id, $user->id);

    $payment = $paymentService->post($payment, $user->id);
    $payment = $paymentService->reconcile($payment, $user->id);

    $event = IntegrationEvent::query()
        ->where('company_id', $company->id)
        ->where('event_type', WebhookEventCatalog::ACCOUNTING_PAYMENT_RECEIVED)
        ->latest('created_at')
        ->first();

    expect($event)->not->toBeNull();
    expect(data_get($event?->payload, 'data.payment_number'))->toBe($payment->payment_number);
    expect(data_get($event?->payload, 'data.invoice_number'))->toBe($invoice->invoice_number);

    $delivery = WebhookDelivery::query()
        ->where('webhook_endpoint_id', $endpoint->id)
        ->where('integration_event_id', $event?->id)
        ->first();

    expect($delivery)->not->toBeNull();
    expect($delivery?->status)->toBe(WebhookDelivery::STATUS_DELIVERED);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint, $payment) {
        $payload = json_decode($request->body(), true);

        return $request->url() === $endpoint->target_url
            && $request->hasHeader('X-Port101-Event', WebhookEventCatalog::ACCOUNTING_PAYMENT_RECEIVED)
            && ($payload['data']['payment_number'] ?? null) === $payment->payment_number
            && ($payload['data']['status'] ?? null) === $payment->status;
    });
});

test('delivery completion publishes inventory delivery webhooks', function () {
    Http::fake();

    [$user, $company] = makeActiveCompanyMember();
    $partner = createWebhookDeliveryPartner($company->id, $user->id);
    $product = createWebhookDeliveryProduct($company->id, $user->id, 'stock');
    $endpoint = createWebhookDeliveryEndpoint($company->id, $user->id, [
        WebhookEventCatalog::INVENTORY_DELIVERY_COMPLETED,
    ]);

    app(InventorySetupService::class)->ensureDefaults($company->id, $user->id);

    $stockLocation = InventoryLocation::query()
        ->where('company_id', $company->id)
        ->where('code', 'STOCK')
        ->firstOrFail();

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'location_id' => $stockLocation->id,
        'product_id' => $product->id,
        'on_hand_quantity' => 10,
        'reserved_quantity' => 0,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $order = app(SalesOrderWorkflowService::class)->create([
        'partner_id' => $partner->id,
        'order_date' => now()->toDateString(),
        'lines' => [[
            'product_id' => $product->id,
            'description' => 'Webhook delivery order',
            'quantity' => 3,
            'unit_price' => 150,
            'discount_percent' => 0,
            'tax_rate' => 0,
        ]],
    ], $user);

    app(SalesOrderWorkflowService::class)->confirm($order, $user);

    $move = InventoryStockMove::query()
        ->where('company_id', $company->id)
        ->where('related_sales_order_id', $order->id)
        ->where('move_type', InventoryStockMove::TYPE_DELIVERY)
        ->latest('created_at')
        ->firstOrFail();

    expect($move->status)->toBe(InventoryStockMove::STATUS_RESERVED);

    $move = app(InventoryStockWorkflowService::class)->dispatch($move, $user->id);

    $event = IntegrationEvent::query()
        ->where('company_id', $company->id)
        ->where('event_type', WebhookEventCatalog::INVENTORY_DELIVERY_COMPLETED)
        ->latest('created_at')
        ->first();

    expect($event)->not->toBeNull();
    expect(data_get($event?->payload, 'data.object_id'))->toBe((string) $move->id);
    expect(data_get($event?->payload, 'data.sales_order_number'))->toBe($order->order_number);

    $delivery = WebhookDelivery::query()
        ->where('webhook_endpoint_id', $endpoint->id)
        ->where('integration_event_id', $event?->id)
        ->first();

    expect($delivery)->not->toBeNull();
    expect($delivery?->status)->toBe(WebhookDelivery::STATUS_DELIVERED);

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($endpoint, $move) {
        $payload = json_decode($request->body(), true);

        return $request->url() === $endpoint->target_url
            && $request->hasHeader('X-Port101-Event', WebhookEventCatalog::INVENTORY_DELIVERY_COMPLETED)
            && ($payload['data']['reference'] ?? null) === $move->reference
            && (float) ($payload['data']['quantity'] ?? 0) === (float) $move->quantity;
    });
});
