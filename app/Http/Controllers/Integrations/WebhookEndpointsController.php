<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrations\WebhookEndpointStoreRequest;
use App\Http\Requests\Integrations\WebhookEndpointUpdateRequest;
use App\Models\User;
use App\Modules\Integrations\IntegrationWorkspaceService;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookDeliveryService;
use App\Modules\Integrations\WebhookEndpointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookEndpointsController extends Controller
{
    public function __construct(
        private readonly WebhookEndpointService $endpointService,
        private readonly WebhookDeliveryService $deliveryService,
        private readonly IntegrationWorkspaceService $workspaceService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:160'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $event = trim((string) ($filters['event'] ?? ''));
        $isActive = ($filters['is_active'] ?? '') === ''
            ? null
            : (bool) (int) $filters['is_active'];

        $query = WebhookEndpoint::query()
            ->with('latestDelivery')
            ->withCount('deliveries')
            ->withCount([
                'deliveries as delivered_deliveries_count' => fn ($builder) => $builder
                    ->where('status', WebhookDelivery::STATUS_DELIVERED),
                'deliveries as failed_deliveries_count' => fn ($builder) => $builder
                    ->whereIn('status', [
                        WebhookDelivery::STATUS_FAILED,
                        WebhookDelivery::STATUS_DEAD,
                    ]),
                'deliveries as dead_deliveries_count' => fn ($builder) => $builder
                    ->where('status', WebhookDelivery::STATUS_DEAD),
            ])
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('target_url', 'like', "%{$search}%");
                });
            })
            ->when($event !== '', function ($builder) use ($event): void {
                $builder->where(function ($nested) use ($event): void {
                    $nested->whereJsonContains('subscribed_events', $event)
                        ->orWhereJsonContains('subscribed_events', '*');
                });
            })
            ->when($isActive !== null, fn ($builder) => $builder->where('is_active', $isActive))
            ->latest('updated_at');

        $user->applyDataScopeToQuery($query);

        $endpoints = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (WebhookEndpoint $endpoint) => $this->workspaceService->mapEndpoint($endpoint, $user));

        return Inertia::render('integrations/webhooks/index', [
            'filters' => [
                'search' => $search,
                'event' => $event,
                'is_active' => $filters['is_active'] ?? '',
            ],
            'eventOptions' => $this->workspaceService->eventOptions(),
            'endpoints' => $endpoints,
            'abilities' => [
                'can_create' => $user->can('create', WebhookEndpoint::class),
                'can_view_deliveries' => $user->can('viewAny', WebhookDelivery::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', WebhookEndpoint::class);

        return Inertia::render('integrations/webhooks/create', [
            'eventOptions' => $this->workspaceService->eventOptions(),
        ]);
    }

    public function store(WebhookEndpointStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', WebhookEndpoint::class);

        $user = $request->user();
        $companyId = (string) $user?->current_company_id;

        if (! $user instanceof User || $companyId === '') {
            abort(403, 'Company context not available.');
        }

        $created = $this->endpointService->create(
            companyId: $companyId,
            attributes: $request->validated(),
            actorId: $user->id,
        );

        /** @var WebhookEndpoint $endpoint */
        $endpoint = $created['endpoint'];

        return redirect()
            ->route('company.integrations.webhooks.show', $endpoint)
            ->with('success', 'Webhook endpoint created.')
            ->with('webhook_signing_secret', $created['signing_secret']);
    }

    public function show(WebhookEndpoint $endpoint, Request $request): Response
    {
        $this->authorize('view', $endpoint);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'event_type' => ['nullable', 'string', 'max:160'],
        ]);

        $status = trim((string) ($filters['status'] ?? ''));
        $eventType = trim((string) ($filters['event_type'] ?? ''));

        $endpoint->load('latestDelivery')
            ->loadCount('deliveries')
            ->loadCount([
                'deliveries as delivered_deliveries_count' => fn ($builder) => $builder
                    ->where('status', WebhookDelivery::STATUS_DELIVERED),
                'deliveries as failed_deliveries_count' => fn ($builder) => $builder
                    ->whereIn('status', [
                        WebhookDelivery::STATUS_FAILED,
                        WebhookDelivery::STATUS_DEAD,
                    ]),
                'deliveries as dead_deliveries_count' => fn ($builder) => $builder
                    ->where('status', WebhookDelivery::STATUS_DEAD),
            ]);

        $deliveryQuery = WebhookDelivery::query()
            ->with('integrationEvent:id,event_type,payload,occurred_at')
            ->where('webhook_endpoint_id', $endpoint->id)
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($eventType !== '', fn ($builder) => $builder->where('event_type', $eventType))
            ->latest('created_at');

        $user->applyDataScopeToQuery($deliveryQuery);

        $deliveries = $deliveryQuery
            ->paginate(15)
            ->withQueryString()
            ->through(fn (WebhookDelivery $delivery) => $this->workspaceService->mapDelivery($delivery, $user));

        $summaryBase = WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->id);
        $user->applyDataScopeToQuery($summaryBase);

        return Inertia::render('integrations/webhooks/show', [
            'endpoint' => $this->workspaceService->mapEndpoint(
                endpoint: $endpoint,
                user: $user,
                revealedSigningSecret: $request->session()->get('webhook_signing_secret'),
            ),
            'filters' => [
                'status' => $status,
                'event_type' => $eventType,
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
            'deliveryStatusOptions' => $this->workspaceService->deliveryStatusOptions(),
            'eventOptions' => $this->workspaceService->eventOptions(false, true),
            'deliveries' => $deliveries,
        ]);
    }

    public function edit(WebhookEndpoint $endpoint): Response
    {
        $this->authorize('update', $endpoint);

        return Inertia::render('integrations/webhooks/edit', [
            'endpoint' => $this->workspaceService->mapEndpoint($endpoint, request()->user()),
            'eventOptions' => $this->workspaceService->eventOptions(),
        ]);
    }

    public function update(
        WebhookEndpointUpdateRequest $request,
        WebhookEndpoint $endpoint,
    ): RedirectResponse {
        $this->authorize('update', $endpoint);

        $this->endpointService->update(
            endpoint: $endpoint,
            attributes: $request->validated(),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.integrations.webhooks.show', $endpoint)
            ->with('success', 'Webhook endpoint updated.');
    }

    public function destroy(WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorize('delete', $endpoint);

        $endpoint->delete();

        return redirect()
            ->route('company.integrations.webhooks.index')
            ->with('success', 'Webhook endpoint removed.');
    }

    public function rotateSecret(WebhookEndpoint $endpoint, Request $request): RedirectResponse
    {
        $this->authorize('rotateSecret', $endpoint);

        $rotated = $this->endpointService->rotateSecret($endpoint, $request->user()?->id);

        return redirect()
            ->route('company.integrations.webhooks.show', $endpoint)
            ->with('success', 'Webhook signing secret rotated.')
            ->with('webhook_signing_secret', $rotated['signing_secret']);
    }

    public function test(WebhookEndpoint $endpoint, Request $request): RedirectResponse
    {
        $this->authorize('test', $endpoint);

        $delivery = $this->deliveryService->sendTest($endpoint, $request->user()?->id);

        [$flashKey, $message] = $this->deliveryOutcomeMessage($delivery);

        return redirect()
            ->route('company.integrations.webhooks.show', $endpoint)
            ->with($flashKey, $message);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function deliveryOutcomeMessage(WebhookDelivery $delivery): array
    {
        return match ($delivery->status) {
            WebhookDelivery::STATUS_DELIVERED => ['success', 'Webhook test delivery succeeded.'],
            WebhookDelivery::STATUS_FAILED => ['warning', 'Webhook test delivery failed. Retry is scheduled.'],
            WebhookDelivery::STATUS_DEAD => ['warning', 'Webhook test delivery failed and is now in the dead-letter queue.'],
            WebhookDelivery::STATUS_PROCESSING,
            WebhookDelivery::STATUS_PENDING => ['success', 'Webhook test delivery queued.'],
            default => ['success', 'Webhook test delivery queued.'],
        };
    }
}
