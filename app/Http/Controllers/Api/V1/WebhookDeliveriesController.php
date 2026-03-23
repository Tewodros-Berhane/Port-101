<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\WebhookDeliveryService;
use Illuminate\Http\JsonResponse;

class WebhookDeliveriesController extends ApiController
{
    public function show(WebhookDelivery $delivery, \Illuminate\Http\Request $request): JsonResponse
    {
        $this->authorize('view', $delivery);

        $delivery->load([
            'endpoint:id,name,target_url,is_active',
            'integrationEvent:id,event_type,payload,occurred_at',
        ]);

        return $this->respond($this->mapDelivery($delivery, $request->user()));
    }

    public function retry(
        WebhookDelivery $delivery,
        \Illuminate\Http\Request $request,
        WebhookDeliveryService $deliveryService,
    ): JsonResponse {
        $this->authorize('retry', $delivery);

        $delivery = $deliveryService->retry($delivery, $request->user()?->id);
        $delivery->load([
            'endpoint:id,name,target_url,is_active',
            'integrationEvent:id,event_type,payload,occurred_at',
        ]);

        return $this->respond($this->mapDelivery($delivery, $request->user()));
    }

    private function mapDelivery(WebhookDelivery $delivery, ?User $user = null): array
    {
        return [
            'id' => $delivery->id,
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'endpoint_name' => $delivery->endpoint?->name,
            'endpoint_url' => $delivery->endpoint?->target_url,
            'integration_event_id' => $delivery->integration_event_id,
            'event_type' => $delivery->event_type,
            'event_payload' => $delivery->integrationEvent?->payload ?? [],
            'occurred_at' => $delivery->integrationEvent?->occurred_at?->toIso8601String(),
            'status' => $delivery->status,
            'attempt_count' => (int) $delivery->attempt_count,
            'last_attempt_at' => $delivery->last_attempt_at?->toIso8601String(),
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'response_status' => $delivery->response_status,
            'duration_ms' => $delivery->duration_ms,
            'response_body_excerpt' => $delivery->response_body_excerpt,
            'failure_message' => $delivery->failure_message,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
            'updated_at' => $delivery->updated_at?->toIso8601String(),
            'can_retry' => $user?->can('retry', $delivery) ?? false,
        ];
    }
}
