<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Integrations\WebhookEndpointStoreRequest;
use App\Http\Requests\Integrations\WebhookEndpointUpdateRequest;
use App\Models\User;
use App\Modules\Integrations\IntegrationWorkspaceService;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookDeliveryService;
use App\Modules\Integrations\WebhookEndpointService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookEndpointsController extends ApiController
{
    public function __construct(
        private readonly WebhookEndpointService $endpointService,
        private readonly WebhookDeliveryService $deliveryService,
        private readonly IntegrationWorkspaceService $workspaceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $event = trim((string) $request->input('event', ''));
        $isActive = $this->booleanFilter($request, 'is_active');
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'updated_at', 'name', 'target_url', 'last_tested_at', 'last_success_at', 'last_failure_at'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $endpoints = WebhookEndpoint::query()
            ->with('latestDelivery')
            ->withCount('deliveries')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('target_url', 'like', "%{$search}%");
                });
            })
            ->when($event !== '', function ($query) use ($event): void {
                $query->where(function ($builder) use ($event): void {
                    $builder->whereJsonContains('subscribed_events', $event)
                        ->orWhereJsonContains('subscribed_events', '*');
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respond(
            data: collect($endpoints->items())
                ->map(fn (WebhookEndpoint $endpoint) => $this->workspaceService->mapEndpoint($endpoint, $user))
                ->all(),
            meta: [
                ...ApiQuery::paginationMeta($endpoints, $sort, $direction, [
                    'search' => $search,
                    'event' => $event,
                    'is_active' => $isActive,
                ]),
                'available_events' => $this->workspaceService->eventOptions(),
                'delivery_security_policy' => $this->workspaceService->deliverySecurityPolicy(),
            ],
        );
    }

    public function store(WebhookEndpointStoreRequest $request): JsonResponse
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
        $endpoint = $created['endpoint']->load('latestDelivery')->loadCount('deliveries');

        return $this->respond(
            data: $this->workspaceService->mapEndpoint(
                endpoint: $endpoint,
                user: $user,
                revealedSigningSecret: (string) $created['signing_secret'],
                analytics: $this->workspaceService->endpointAnalytics($endpoint),
                rotations: $this->workspaceService->recentRotations($endpoint),
            ),
            status: 201,
        );
    }

    public function show(WebhookEndpoint $endpoint, Request $request): JsonResponse
    {
        $this->authorize('view', $endpoint);

        $user = $request->user();

        $endpoint->load('latestDelivery')
            ->loadCount('deliveries');

        return $this->respond($this->workspaceService->mapEndpoint(
            endpoint: $endpoint,
            user: $user,
            analytics: $this->workspaceService->endpointAnalytics($endpoint),
            rotations: $this->workspaceService->recentRotations($endpoint),
        ));
    }

    public function update(
        WebhookEndpointUpdateRequest $request,
        WebhookEndpoint $endpoint,
    ): JsonResponse {
        $this->authorize('update', $endpoint);

        $user = $request->user();

        $endpoint = $this->endpointService->update(
            endpoint: $endpoint,
            attributes: $request->validated(),
            actorId: $user?->id,
        );

        $endpoint->load('latestDelivery')->loadCount('deliveries');

        return $this->respond($this->workspaceService->mapEndpoint(
            endpoint: $endpoint,
            user: $user,
            analytics: $this->workspaceService->endpointAnalytics($endpoint),
            rotations: $this->workspaceService->recentRotations($endpoint),
        ));
    }

    public function destroy(WebhookEndpoint $endpoint): JsonResponse
    {
        $this->authorize('delete', $endpoint);

        $endpoint->delete();

        return $this->respondNoContent();
    }

    public function rotateSecret(WebhookEndpoint $endpoint, Request $request): JsonResponse
    {
        $this->authorize('rotateSecret', $endpoint);

        $rotated = $this->endpointService->rotateSecret($endpoint, $request->user()?->id);

        /** @var WebhookEndpoint $freshEndpoint */
        $freshEndpoint = $rotated['endpoint']->load('latestDelivery')->loadCount('deliveries');

        return $this->respond(
            $this->workspaceService->mapEndpoint(
                endpoint: $freshEndpoint,
                user: $request->user(),
                revealedSigningSecret: (string) $rotated['signing_secret'],
                analytics: $this->workspaceService->endpointAnalytics($freshEndpoint),
                rotations: $this->workspaceService->recentRotations($freshEndpoint),
            ),
        );
    }

    public function test(WebhookEndpoint $endpoint, Request $request): JsonResponse
    {
        $this->authorize('test', $endpoint);

        $delivery = $this->deliveryService->sendTest($endpoint, $request->user()?->id);

        return $this->respond($this->workspaceService->mapDelivery($delivery, $request->user()));
    }

    public function deliveries(WebhookEndpoint $endpoint, Request $request): JsonResponse
    {
        $this->authorize('view', $endpoint);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $status = trim((string) $request->input('status', ''));
        $eventType = trim((string) $request->input('event_type', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['created_at', 'updated_at', 'last_attempt_at', 'delivered_at', 'status', 'attempt_count', 'response_status'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $deliveries = WebhookDelivery::query()
            ->with('integrationEvent')
            ->where('webhook_endpoint_id', $endpoint->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($eventType !== '', fn ($query) => $query->where('event_type', $eventType))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respond(
            data: collect($deliveries->items())
                ->map(fn (WebhookDelivery $delivery) => $this->workspaceService->mapDelivery($delivery, $user))
                ->all(),
            meta: [
                ...ApiQuery::paginationMeta($deliveries, $sort, $direction, [
                    'status' => $status,
                    'event_type' => $eventType,
                ]),
                'endpoint_id' => $endpoint->id,
                'delivery_security_policy' => $this->workspaceService->deliverySecurityPolicy(),
            ],
        );
    }

    private function booleanFilter(Request $request, string $key): ?bool
    {
        if (! $request->query->has($key)) {
            return null;
        }

        return filter_var($request->query($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
