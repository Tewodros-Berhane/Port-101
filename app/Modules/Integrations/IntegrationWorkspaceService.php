<?php

namespace App\Modules\Integrations;

use App\Models\User;
use App\Support\Operations\OperationalFailureSanitizer;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookSecretRotation;
use Illuminate\Database\Eloquent\Builder;

class IntegrationWorkspaceService
{
    public function __construct(
        private readonly WebhookEventCatalog $eventCatalog,
        private readonly WebhookEndpointService $endpointService,
        private readonly OperationalFailureSanitizer $failureSanitizer,
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
     * @return array<string, int|float|null>
     */
    public function moduleSummary(User $user): array
    {
        $endpointQuery = WebhookEndpoint::query();
        $deliveryQuery = WebhookDelivery::query();

        $user->applyDataScopeToQuery($endpointQuery);
        $user->applyDataScopeToQuery($deliveryQuery);

        $sevenDaysAgo = now()->subDays(7);
        $deliveredLastSevenDays = (clone $deliveryQuery)
            ->where('status', WebhookDelivery::STATUS_DELIVERED)
            ->where('delivered_at', '>=', $sevenDaysAgo)
            ->count();
        $failedLastSevenDays = (clone $deliveryQuery)
            ->whereIn('status', [
                WebhookDelivery::STATUS_FAILED,
                WebhookDelivery::STATUS_DEAD,
            ])
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();
        $totalAttemptsLastSevenDays = $deliveredLastSevenDays + $failedLastSevenDays;
        $avgDurationLastSevenDays = (clone $deliveryQuery)
            ->whereNotNull('duration_ms')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->avg('duration_ms');

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
            'delivered_last_7_days' => $deliveredLastSevenDays,
            'dead_letters' => (clone $deliveryQuery)
                ->where('status', WebhookDelivery::STATUS_DEAD)
                ->count(),
            'pending_retries' => (clone $deliveryQuery)
                ->where('status', WebhookDelivery::STATUS_FAILED)
                ->whereNotNull('next_retry_at')
                ->count(),
            'success_rate_last_7_days' => $totalAttemptsLastSevenDays > 0
                ? round(($deliveredLastSevenDays / $totalAttemptsLastSevenDays) * 100, 1)
                : null,
            'average_duration_ms_last_7_days' => $avgDurationLastSevenDays !== null
                ? (float) round($avgDurationLastSevenDays, 1)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapEndpoint(
        WebhookEndpoint $endpoint,
        ?User $user = null,
        ?string $revealedSigningSecret = null,
        ?array $analytics = null,
        ?array $rotations = null,
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
            'signing_secret_version' => (int) $endpoint->signing_secret_version,
            'secret_preview' => $this->endpointService->secretPreview($endpoint),
            'revealed_signing_secret' => $revealedSigningSecret,
            'secret_rotated_at' => $endpoint->secret_rotated_at?->toIso8601String(),
            'deliveries_count' => (int) ($endpoint->deliveries_count ?? $endpoint->deliveries()->count()),
            'delivered_deliveries_count' => (int) ($endpoint->delivered_deliveries_count ?? 0),
            'failed_deliveries_count' => (int) ($endpoint->failed_deliveries_count ?? 0),
            'dead_deliveries_count' => (int) ($endpoint->dead_deliveries_count ?? 0),
            'consecutive_failure_count' => (int) $endpoint->consecutive_failure_count,
            'health_status' => $this->endpointHealthStatus($endpoint),
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
            'last_delivery_at' => $endpoint->last_delivery_at?->toIso8601String(),
            'delivery_security_policy' => $this->deliverySecurityPolicy(),
            'analytics' => $analytics ?? [],
            'recent_secret_rotations' => $rotations ?? [],
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
            'first_attempt_at' => $delivery->first_attempt_at?->toIso8601String(),
            'last_attempt_at' => $delivery->last_attempt_at?->toIso8601String(),
            'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
            'response_status' => $delivery->response_status,
            'duration_ms' => $delivery->duration_ms,
            'response_body_excerpt' => $this->failureSanitizer->sanitizeStoredWebhookResponseExcerpt(
                $delivery->response_body_excerpt
            ),
            'failure_message' => $this->failureSanitizer->sanitizeStoredWebhookFailureMessage(
                $delivery->failure_message,
                $delivery->response_status,
            ),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'dead_lettered_at' => $delivery->dead_lettered_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
            'updated_at' => $delivery->updated_at?->toIso8601String(),
            'can_retry' => $user?->can('retry', $delivery) ?? false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function endpointAnalytics(WebhookEndpoint $endpoint): array
    {
        $deliveryQuery = WebhookDelivery::query()
            ->where('webhook_endpoint_id', $endpoint->id);

        $sevenDaysAgo = now()->subDays(7);
        $deliveredLastSevenDays = (clone $deliveryQuery)
            ->where('status', WebhookDelivery::STATUS_DELIVERED)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();
        $failedLastSevenDays = (clone $deliveryQuery)
            ->whereIn('status', [
                WebhookDelivery::STATUS_FAILED,
                WebhookDelivery::STATUS_DEAD,
            ])
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();
        $totalLastSevenDays = $deliveredLastSevenDays + $failedLastSevenDays;
        $averageDuration = (clone $deliveryQuery)
            ->whereNotNull('duration_ms')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->avg('duration_ms');
        $lastDeadLetterAt = (clone $deliveryQuery)
            ->where('status', WebhookDelivery::STATUS_DEAD)
            ->orderByDesc('dead_lettered_at')
            ->first()?->dead_lettered_at;

        return [
            'total_deliveries' => (clone $deliveryQuery)->count(),
            'delivered_last_7_days' => $deliveredLastSevenDays,
            'failed_last_7_days' => $failedLastSevenDays,
            'dead_letters' => (clone $deliveryQuery)
                ->where('status', WebhookDelivery::STATUS_DEAD)
                ->count(),
            'pending_retries' => (clone $deliveryQuery)
                ->where('status', WebhookDelivery::STATUS_FAILED)
                ->whereNotNull('next_retry_at')
                ->count(),
            'success_rate_last_7_days' => $totalLastSevenDays > 0
                ? round(($deliveredLastSevenDays / $totalLastSevenDays) * 100, 1)
                : null,
            'average_duration_ms_last_7_days' => $averageDuration !== null
                ? (float) round($averageDuration, 1)
                : null,
            'last_dead_letter_at' => $lastDeadLetterAt?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentRotations(WebhookEndpoint $endpoint, int $limit = 5): array
    {
        return WebhookSecretRotation::query()
            ->where('webhook_endpoint_id', $endpoint->id)
            ->latest('rotated_at')
            ->limit($limit)
            ->get()
            ->map(fn (WebhookSecretRotation $rotation) => $this->mapRotation($rotation))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRotation(WebhookSecretRotation $rotation): array
    {
        return [
            'id' => $rotation->id,
            'secret_version' => (int) $rotation->secret_version,
            'reason' => $rotation->reason,
            'previous_secret_preview' => $rotation->previous_secret_preview,
            'current_secret_preview' => $rotation->current_secret_preview,
            'current_secret_fingerprint' => $rotation->current_secret_fingerprint,
            'rotated_at' => $rotation->rotated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deliverySecurityPolicy(): array
    {
        return $this->endpointService->deliverySecurityPolicy();
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

    private function endpointHealthStatus(WebhookEndpoint $endpoint): string
    {
        if (! $endpoint->is_active) {
            return 'inactive';
        }

        if ((int) $endpoint->consecutive_failure_count >= 3) {
            return 'degraded';
        }

        if ($endpoint->last_failure_at && (! $endpoint->last_success_at || $endpoint->last_failure_at->gt($endpoint->last_success_at))) {
            return 'warning';
        }

        return 'healthy';
    }
}
