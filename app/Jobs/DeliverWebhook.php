<?php

namespace App\Jobs;

use App\Modules\Integrations\WebhookDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $webhookDeliveryId)
    {
    }

    public function handle(WebhookDeliveryService $deliveryService): void
    {
        $deliveryService->deliverById($this->webhookDeliveryId);
    }
}
