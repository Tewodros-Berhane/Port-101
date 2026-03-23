<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Integrations\WebhookEndpointStoreRequest;
use App\Http\Requests\Integrations\WebhookEndpointUpdateRequest;
use App\Models\User;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\WebhookDeliveryService;
use App\Modules\Integrations\WebhookEndpointService;
use App\Modules\Integrations\WebhookEventCatalog;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookEndpointsController extends ApiController
{
    public function __construct(
        private readonly WebhookEndpointService $endpointService,
        private readonly WebhookDeliveryService $deliveryService,
        private readonly WebhookEventCatalog $eventCatalog,
    ) {
    }

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
                ->map(fn (WebhookEndpoint $endpoint) => $this->mapEndpoint($endpoint, $user))
                ->all(),
            meta: [
                ...ApiQuery::paginationMeta($endpoints, $sort, $direction, [
                    'search' => $search,
                    'event' => $event,
                    'is_active' => $isActive,
                ]),
                'available_events' => $this->eventCatalog->options(),
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
            data: $this->mapEndpoint($endpoint, $user, (string) $created['signing_secret']),
            status: 201,
        );
    }

    public function show(WebhookEndpoint $endpoint, Request $request): JsonResponse
    {
        $this->authorize('view', $endpoint);

        $user = $request->user();

        $endpoint->load('latestDelivery')
            ->loadCount('deliveries');

        return $this->respond($this->mapEndpoint($endpoint, $user));
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

        return $this->respond($this->mapEndpoint($endpoint, $user));
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
            $this->mapEndpoint(
                endpoint: $freshEndpoint,
                user: $request->user(),
                revealedSigningSecret: (string) $rotated['signing_secret'],
            ),
        );
    }

    public function test(WebhookEndpoint $endpoint, Request $request): JsonResponse
    {
        $this->authorize('test', $endpoint);

        $delivery = $this->deliveryService->sendTest($endpoint, $request->user()?->id);

        return $this->respond($this->mapDelivery($delivery, $request->user()));
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
                ->map(fn (WebhookDelivery $delivery) => $this->mapDelivery($delivery, $user))
                ->all(),
            meta: [
                ...ApiQuery::paginationMeta($deliveries, $sort, $direction, [
                    'status' => $status,
                    'event_type' => $eventType,
                ]),
                'endpoint_id' => $endpoint->id,
            ],
        );
    }

    private function mapEndpoint(
        WebhookEndpoint $endpoint,
        ?User $user = null,
        ?string $revealedSigningSecret = null,
    ): array {
        $latestDelivery = $endpoint->relationLoaded('latestDelivery')
            ? $endpoint->latestDelivery
            : null;

        return [
            'id' => $endpoint->id,
            'name' => $endpoint->name,
            'target_url' => $endpoint->target_url,
            'api_version' => $endpoint->api_version,
            'is_active' => (bool) $endpoint->is_active,
            'subscribed_events' => $endpoint->subscribed_events ?? [],
            'subscribed_event_labels' => collect($endpoint->subscribed_events ?? [])
                ->map(fn (string $event) => $event === '*'
                    ? 'All supported events'
                    : $this->eventCatalog->label($event))
                ->values()
                ->all(),
            'secret_preview' => $this->endpointService->secretPreview($endpoint),
            'revealed_signing_secret' => $revealedSigningSecret,
            'deliveries_count' => (int) ($endpoint->deliveries_count ?? $endpoint->deliveries()->count()),
            'latest_delivery' => $latestDelivery
                ? [
                    'id' => $latestDelivery->id,
                    'event_type' => $latestDelivery->event_type,
                    'status' => $latestDelivery->status,
                    'response_status' => $latestDelivery->response_status,
                    'delivered_at' => $latestDelivery->delivered_at?->toIso8601String(),
                ]
                : null,
            'last_tested_at' => $endpoint->last_tested_at?->toIso8601String(),
            'last_success_at' => $endpoint->last_success_at?->toIso8601String(),
            'last_failure_at' => $endpoint->last_failure_at?->toIso8601String(),
            'created_at' => $endpoint->created_at?->toIso8601String(),
            'updated_at' => $endpoint->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $endpoint) ?? false,
            'can_edit' => $user?->can('update', $endpoint) ?? false,
            'can_delete' => $user?->can('delete', $endpoint) ?? false,
            'can_rotate_secret' => $user?->can('rotateSecret', $endpoint) ?? false,
            'can_test' => $user?->can('test', $endpoint) ?? false,
        ];
    }

    private function mapDelivery(WebhookDelivery $delivery, ?User $user = null): array
    {
        return [
            'id' => $delivery->id,
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'integration_event_id' => $delivery->integration_event_id,
            'event_type' => $delivery->event_type,
            'status' => $delivery->status,
            'attempt_count' => (int) $delivery->attempt_count,
            'last_attempt_at' => $delivery->last_attempt_at?->toIso8601String(),
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'response_status' => $delivery->response_status,
            'duration_ms' => $delivery->duration_ms,
            'response_body_excerpt' => $delivery->response_body_excerpt,
            'failure_message' => $delivery->failure_message,
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'event_payload' => $delivery->integrationEvent?->payload ?? [],
            'created_at' => $delivery->created_at?->toIso8601String(),
            'updated_at' => $delivery->updated_at?->toIso8601String(),
            'can_retry' => $user?->can('retry', $delivery) ?? false,
        ];
    }

    private function booleanFilter(Request $request, string $key): ?bool
    {
        if (! $request->query->has($key)) {
            return null;
        }

        return filter_var($request->query($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
