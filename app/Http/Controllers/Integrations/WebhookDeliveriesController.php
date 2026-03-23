<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Integrations\IntegrationWorkspaceService;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookDeliveriesController extends Controller
{
    public function __construct(
        private readonly WebhookDeliveryService $deliveryService,
        private readonly IntegrationWorkspaceService $workspaceService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WebhookDelivery::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'event_type' => ['nullable', 'string', 'max:160'],
            'endpoint_id' => ['nullable', 'string', 'max:64'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $eventType = trim((string) ($filters['event_type'] ?? ''));
        $endpointId = trim((string) ($filters['endpoint_id'] ?? ''));

        $query = WebhookDelivery::query()
            ->with([
                'endpoint:id,name,target_url,is_active',
                'integrationEvent:id,event_type,payload,occurred_at',
            ])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('event_type', 'like', "%{$search}%")
                        ->orWhereHas('endpoint', function ($endpointQuery) use ($search): void {
                            $endpointQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('target_url', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($eventType !== '', fn ($builder) => $builder->where('event_type', $eventType))
            ->when($endpointId !== '', fn ($builder) => $builder->where('webhook_endpoint_id', $endpointId))
            ->latest('created_at');

        $user->applyDataScopeToQuery($query);

        $deliveries = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (WebhookDelivery $delivery) => $this->workspaceService->mapDelivery($delivery, $user));

        $summaryBase = WebhookDelivery::query();
        $user->applyDataScopeToQuery($summaryBase);

        $endpointOptions = WebhookEndpoint::query()
            ->orderBy('name')
            ->tap(fn ($builder) => $user->applyDataScopeToQuery($builder))
            ->get(['id', 'name'])
            ->map(fn (WebhookEndpoint $endpoint) => [
                'id' => $endpoint->id,
                'name' => $endpoint->name,
            ])
            ->values()
            ->all();

        return Inertia::render('integrations/deliveries/index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'event_type' => $eventType,
                'endpoint_id' => $endpointId,
            ],
            'summary' => [
                'total' => (clone $summaryBase)->count(),
                'delivered' => (clone $summaryBase)
                    ->where('status', WebhookDelivery::STATUS_DELIVERED)
                    ->count(),
                'failed' => (clone $summaryBase)
                    ->where('status', WebhookDelivery::STATUS_FAILED)
                    ->count(),
                'dead' => (clone $summaryBase)
                    ->where('status', WebhookDelivery::STATUS_DEAD)
                    ->count(),
                'pending' => (clone $summaryBase)
                    ->whereIn('status', [
                        WebhookDelivery::STATUS_PENDING,
                        WebhookDelivery::STATUS_PROCESSING,
                    ])
                    ->count(),
            ],
            'statusOptions' => $this->workspaceService->deliveryStatusOptions(),
            'eventOptions' => $this->workspaceService->eventOptions(false, true),
            'endpointOptions' => $endpointOptions,
            'deliveries' => $deliveries,
        ]);
    }

    public function show(WebhookDelivery $delivery, Request $request): Response
    {
        $this->authorize('view', $delivery);

        $delivery->load([
            'endpoint:id,name,target_url,is_active',
            'integrationEvent:id,event_type,payload,occurred_at',
        ]);

        return Inertia::render('integrations/deliveries/show', [
            'delivery' => $this->workspaceService->mapDelivery($delivery, $request->user()),
        ]);
    }

    public function retry(WebhookDelivery $delivery, Request $request): RedirectResponse
    {
        $this->authorize('retry', $delivery);

        $delivery = $this->deliveryService->retry($delivery, $request->user()?->id);

        [$flashKey, $message] = $this->retryOutcomeMessage($delivery);

        return redirect()
            ->route('company.integrations.deliveries.show', $delivery)
            ->with($flashKey, $message);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function retryOutcomeMessage(WebhookDelivery $delivery): array
    {
        return match ($delivery->status) {
            WebhookDelivery::STATUS_DELIVERED => ['success', 'Webhook delivery retried successfully.'],
            WebhookDelivery::STATUS_FAILED => ['warning', 'Webhook delivery failed again. Another retry is scheduled.'],
            WebhookDelivery::STATUS_DEAD => ['warning', 'Webhook delivery failed again and remains in the dead-letter queue.'],
            WebhookDelivery::STATUS_PROCESSING,
            WebhookDelivery::STATUS_PENDING => ['success', 'Webhook delivery retry queued.'],
            default => ['success', 'Webhook delivery retry queued.'],
        };
    }
}
