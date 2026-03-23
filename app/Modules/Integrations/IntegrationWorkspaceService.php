<?php

namespace App\Modules\Integrations;

use App\Models\User;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Builder;

class IntegrationWorkspaceService
{
    public function __construct(
        private readonly WebhookEventCatalog $eventCatalog,
        private readonly WebhookEndpointService $endpointService,
    ) {}

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function eventOptions(
        bool $includeWildcard = true,
        bool $includeTestEvent = false,
    ): array {
        $options = collect($this->eventCatalog->options())
            ->when(
                ! $includeTestEvent,
                fn ($collection) => $collection->reject(
                    fn (array $option) => $option['value'] === WebhookEventCatalog::SYSTEM_WEBHOOK_TEST
                ),
            );

        if ($includeWildcard) {
            $options->prepend([
                'value' => '*',
                'label' => 'All supported events',
            ]);
        }

        return $options->values()->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function deliveryStatusOptions(): array
    {
        return collect(WebhookDelivery::STATUSES)
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => $this->deliveryStatusLabel($status),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function moduleSummary(User $user): array
    {
        $endpointQuery = WebhookEndpoint::query();
        $deliveryQuery = WebhookDelivery::query();

        $user->applyDataScopeToQuery($endpointQuery);
        $user->applyDataScopeToQuery($deliveryQuery);

        $sevenDaysAgo = now()->subDays(7);

        return [
            'total_endpoints' => (clone $endpointQuery)->count(),
            'active_endpoints' => (clone $endpointQuery)
                ->where('is_active', true)
                ->count(),
            'failing_endpoints' => (clone $endpointQuery)
                ->whereNotNull('last_failure_at')
                ->where(function (Builder $query): void {
                    $query->whereNull('last_success_at')
                        ->orWhereColumn('last_failure_at', '>', 'last_success_at');
                })
                ->count(),
            'delivered_last_7_days' => (clone $deliveryQuery)
                ->where('status', WebhookDelivery::STATUS_DELIVERED)
                ->where('delivered_at', '>=', $sevenDaysAgo)
                ->count(),
            'dead_letters' => (clone $deliveryQuery)
                ->where('status', WebhookDelivery::STATUS_DEAD)
                ->count(),
            'pending_retries' => (clone $deliveryQuery)
                ->where('status', WebhookDelivery::STATUS_FAILED)
                ->whereNotNull('next_retry_at')
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapEndpoint(
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
            'delivered_deliveries_count' => (int) ($endpoint->delivered_deliveries_count ?? 0),
            'failed_deliveries_count' => (int) ($endpoint->failed_deliveries_count ?? 0),
            'dead_deliveries_count' => (int) ($endpoint->dead_deliveries_count ?? 0),
            'latest_delivery' => $latestDelivery
                ? [
                    'id' => $latestDelivery->id,
                    'event_type' => $latestDelivery->event_type,
                    'event_label' => $this->eventCatalog->label((string) $latestDelivery->event_type),
                    'status' => $latestDelivery->status,
                    'status_label' => $this->deliveryStatusLabel((string) $latestDelivery->status),
                    'response_status' => $latestDelivery->response_status,
                    'delivered_at' => $latestDelivery->delivered_at?->toIso8601String(),
                    'created_at' => $latestDelivery->created_at?->toIso8601String(),
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

    /**
     * @return array<string, mixed>
     */
    public function mapDelivery(WebhookDelivery $delivery, ?User $user = null): array
    {
        return [
            'id' => $delivery->id,
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'endpoint_name' => $delivery->endpoint?->name,
            'endpoint_url' => $delivery->endpoint?->target_url,
            'integration_event_id' => $delivery->integration_event_id,
            'event_type' => $delivery->event_type,
            'event_label' => $this->eventCatalog->label((string) $delivery->event_type),
            'event_payload' => $delivery->integrationEvent?->payload ?? [],
            'occurred_at' => $delivery->integrationEvent?->occurred_at?->toIso8601String(),
            'status' => $delivery->status,
            'status_label' => $this->deliveryStatusLabel((string) $delivery->status),
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

    public function deliveryStatusLabel(string $status): string
    {
        return match ($status) {
            WebhookDelivery::STATUS_PENDING => 'Pending',
            WebhookDelivery::STATUS_PROCESSING => 'Processing',
            WebhookDelivery::STATUS_DELIVERED => 'Delivered',
            WebhookDelivery::STATUS_FAILED => 'Retry scheduled',
            WebhookDelivery::STATUS_DEAD => 'Dead letter',
            default => $status,
        };
    }

    public function eventLabel(string $eventType): string
    {
        return $eventType === '*'
            ? 'All supported events'
            : $this->eventCatalog->label($eventType);
    }
}
