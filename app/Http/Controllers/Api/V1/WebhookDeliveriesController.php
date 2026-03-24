<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Integrations\IntegrationWorkspaceService;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\WebhookDeliveryService;
use Illuminate\Http\JsonResponse;

class WebhookDeliveriesController extends ApiController
{
    public function __construct(
        private readonly IntegrationWorkspaceService $workspaceService,
    ) {}

    public function show(WebhookDelivery $delivery, \Illuminate\Http\Request $request): JsonResponse
    {
        $this->authorize('view', $delivery);

        $delivery->load([
            'endpoint:id,name,target_url,is_active',
            'integrationEvent:id,event_type,payload,occurred_at',
        ]);

        return $this->respond(
            data: $this->workspaceService->mapDelivery($delivery, $request->user()),
            meta: [
                'delivery_security_policy' => $this->workspaceService->deliverySecurityPolicy(),
            ],
        );
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

        return $this->respond(
            data: $this->workspaceService->mapDelivery($delivery, $request->user()),
            meta: [
                'delivery_security_policy' => $this->workspaceService->deliverySecurityPolicy(),
            ],
        );
    }
}
