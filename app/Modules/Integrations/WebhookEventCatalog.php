<?php

namespace App\Modules\Integrations;

class WebhookEventCatalog
{
    public const SALES_ORDER_CONFIRMED = 'sales.order.confirmed';

    public const PROJECT_PROVISIONED = 'projects.project.provisioned';

    public const ACCOUNTING_INVOICE_POSTED = 'accounting.invoice.posted';

    public const ACCOUNTING_PAYMENT_RECEIVED = 'accounting.payment.received';

    public const INVENTORY_DELIVERY_COMPLETED = 'inventory.delivery.completed';

    public const SYSTEM_WEBHOOK_TEST = 'system.webhook.test';

    /**
     * @var array<int, string>
     */
    public const EVENTS = [
        self::SALES_ORDER_CONFIRMED,
        self::PROJECT_PROVISIONED,
        self::ACCOUNTING_INVOICE_POSTED,
        self::ACCOUNTING_PAYMENT_RECEIVED,
        self::INVENTORY_DELIVERY_COMPLETED,
        self::SYSTEM_WEBHOOK_TEST,
    ];

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        return collect(self::EVENTS)
            ->map(fn (string $event) => [
                'value' => $event,
                'label' => $this->label($event),
            ])
            ->values()
            ->all();
    }

    public function label(string $event): string
    {
        return match ($event) {
            self::SALES_ORDER_CONFIRMED => 'Sales order confirmed',
            self::PROJECT_PROVISIONED => 'Project provisioned',
            self::ACCOUNTING_INVOICE_POSTED => 'Accounting invoice posted',
            self::ACCOUNTING_PAYMENT_RECEIVED => 'Accounting payment received',
            self::INVENTORY_DELIVERY_COMPLETED => 'Inventory delivery completed',
            self::SYSTEM_WEBHOOK_TEST => 'Webhook test event',
            default => $event,
        };
    }
}
