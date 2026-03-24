<?php

namespace App\Modules\Integrations;

use App\Jobs\DeliverWebhook;
use App\Modules\Integrations\Models\IntegrationEvent;
use App\Modules\Integrations\Models\WebhookDelivery;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WebhookDeliveryService
{
    private const MAX_ATTEMPTS = 5;

    /**
     * @var array<int, int>
     */
    private const BACKOFF_SECONDS = [60, 300, 900, 3600];

    public function __construct(
        private readonly WebhookSignatureService $signatureService,
        private readonly IntegrationEventPayloadFactory $payloadFactory,
    ) {}

    public function fanOutById(string $integrationEventId): ?IntegrationEvent
    {
        /** @var IntegrationEvent|null $integrationEvent */
        $integrationEvent = IntegrationEvent::withoutGlobalScopes()
            ->with('company')
            ->find($integrationEventId);

        if (! $integrationEvent) {
            return null;
        }

        $endpointQuery = WebhookEndpoint::withoutGlobalScopes()
            ->where('company_id', $integrationEvent->company_id)
            ->where('is_active', true)
            ->where(function ($query) use ($integrationEvent): void {
                $query->whereJsonContains('subscribed_events', $integrationEvent->event_type)
                    ->orWhereJsonContains('subscribed_events', '*');
            });

        $endpointQuery->get()->each(function (WebhookEndpoint $endpoint) use ($integrationEvent): void {
            $delivery = WebhookDelivery::withoutGlobalScopes()
                ->firstOrCreate(
                    [
                        'webhook_endpoint_id' => $endpoint->id,
                        'integration_event_id' => $integrationEvent->id,
                    ],
                    [
                        'company_id' => $integrationEvent->company_id,
                        'event_type' => $integrationEvent->event_type,
                        'status' => WebhookDelivery::STATUS_PENDING,
                        'attempt_count' => 0,
                        'created_by' => $integrationEvent->created_by,
                        'updated_by' => $integrationEvent->updated_by,
                    ],
                );

            $this->dispatchDelivery((string) $delivery->id);
        });

        $integrationEvent->update([
            'published_at' => now(),
            'updated_by' => $integrationEvent->updated_by ?? $integrationEvent->created_by,
        ]);

        return $integrationEvent->fresh();
    }

    public function deliverById(string $deliveryId): ?WebhookDelivery
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::withoutGlobalScopes()
            ->with([
                'endpoint',
                'integrationEvent',
            ])
            ->find($deliveryId);

        if (! $delivery) {
            return null;
        }

        $endpoint = $delivery->endpoint;
        $integrationEvent = $delivery->integrationEvent;

        if (! $endpoint || ! $integrationEvent) {
            return $this->markFailed(
                $delivery,
                'Webhook endpoint or integration event no longer exists.',
                null,
                null,
                false,
            );
        }

        if (! $endpoint->is_active) {
            return $this->markFailed(
                $delivery,
                'Webhook endpoint is inactive.',
                null,
                null,
                false,
            );
        }

        $startedAt = microtime(true);
        $delivery->update([
            'status' => WebhookDelivery::STATUS_PROCESSING,
            'first_attempt_at' => $delivery->first_attempt_at ?? now(),
            'last_attempt_at' => now(),
            'attempt_count' => (int) $delivery->attempt_count + 1,
            'updated_by' => $delivery->updated_by ?? $delivery->created_by,
        ]);

        $payload = is_array($integrationEvent->payload)
            ? $integrationEvent->payload
            : [];
        $signed = $this->signatureService->signedPayload(
            endpoint: $endpoint,
            eventId: (string) $integrationEvent->id,
            eventType: (string) $integrationEvent->event_type,
            payload: $payload,
            attemptCount: (int) $delivery->attempt_count,
        );

        try {
            $response = Http::timeout(10)
                ->withHeaders($signed['headers'])
                ->withBody($signed['raw_body'], 'application/json')
                ->post((string) $endpoint->target_url);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($response->successful()) {
                return $this->markDelivered(
                    delivery: $delivery->fresh() ?? $delivery,
                    responseStatus: $response->status(),
                    responseBody: $response->body(),
                    durationMs: $durationMs,
                );
            }

            return $this->markFailed(
                delivery: $delivery->fresh() ?? $delivery,
                failureMessage: 'Endpoint responded with a non-success status.',
                responseStatus: $response->status(),
                responseBody: $response->body(),
                retryable: true,
                durationMs: $durationMs,
            );
        } catch (ConnectionException $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            return $this->markFailed(
                delivery: $delivery->fresh() ?? $delivery,
                failureMessage: $exception->getMessage(),
                responseStatus: null,
                responseBody: null,
                retryable: true,
                durationMs: $durationMs,
            );
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            return $this->markFailed(
                delivery: $delivery->fresh() ?? $delivery,
                failureMessage: $exception->getMessage(),
                responseStatus: null,
                responseBody: null,
                retryable: false,
                durationMs: $durationMs,
            );
        }
    }

    public function retry(WebhookDelivery $delivery, ?string $actorId = null): WebhookDelivery
    {
        $delivery = WebhookDelivery::query()
            ->with('endpoint')
            ->findOrFail($delivery->id);

        if (! in_array($delivery->status, [
            WebhookDelivery::STATUS_FAILED,
            WebhookDelivery::STATUS_DEAD,
        ], true)) {
            abort(422, 'Only failed or dead deliveries can be retried.');
        }

        if (! $delivery->endpoint?->is_active) {
            abort(422, 'Inactive endpoints cannot be retried.');
        }

        $delivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_retry_at' => null,
            'failure_message' => null,
            'dead_lettered_at' => null,
            'updated_by' => $actorId,
        ]);

        $this->dispatchDelivery((string) $delivery->id);

        return $delivery->fresh(['endpoint', 'integrationEvent']) ?? $delivery;
    }

    public function sendTest(WebhookEndpoint $endpoint, ?string $actorId = null): WebhookDelivery
    {
        $eventId = (string) Str::uuid();
        $occurredAt = now();

        $event = new IntegrationEvent([
            'company_id' => (string) $endpoint->company_id,
            'event_type' => WebhookEventCatalog::SYSTEM_WEBHOOK_TEST,
            'aggregate_type' => WebhookEndpoint::class,
            'aggregate_id' => (string) $endpoint->id,
            'occurred_at' => $occurredAt,
            'payload' => $this->payloadFactory->make(
                eventType: WebhookEventCatalog::SYSTEM_WEBHOOK_TEST,
                companyId: (string) $endpoint->company_id,
                data: [
                    'object_type' => 'webhook_endpoint',
                    'object_id' => (string) $endpoint->id,
                    'reference' => $endpoint->name,
                    'status' => $endpoint->is_active ? 'active' : 'inactive',
                    'message' => 'Port-101 webhook connectivity test.',
                ],
                eventId: $eventId,
                occurredAt: $occurredAt,
            ),
            'published_at' => $occurredAt,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $event->id = $eventId;
        $event->save();

        $delivery = WebhookDelivery::create([
            'company_id' => (string) $endpoint->company_id,
            'webhook_endpoint_id' => (string) $endpoint->id,
            'integration_event_id' => (string) $event->id,
            'event_type' => WebhookEventCatalog::SYSTEM_WEBHOOK_TEST,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempt_count' => 0,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->dispatchDelivery((string) $delivery->id);

        $endpoint->update([
            'last_tested_at' => now(),
            'updated_by' => $actorId,
        ]);

        return $delivery->fresh(['endpoint', 'integrationEvent']) ?? $delivery;
    }

    private function markDelivered(
        WebhookDelivery $delivery,
        int $responseStatus,
        string $responseBody,
        ?int $durationMs = null,
    ): WebhookDelivery {
        $delivery->update([
            'status' => WebhookDelivery::STATUS_DELIVERED,
            'response_status' => $responseStatus,
            'duration_ms' => $durationMs,
            'response_body_excerpt' => $this->excerpt($responseBody),
            'failure_message' => null,
            'next_retry_at' => null,
            'delivered_at' => now(),
            'dead_lettered_at' => null,
        ]);

        $delivery->endpoint?->update([
            'last_delivery_at' => now(),
            'last_success_at' => now(),
            'consecutive_failure_count' => 0,
        ]);

        return $delivery->fresh(['endpoint', 'integrationEvent']) ?? $delivery;
    }

    private function markFailed(
        WebhookDelivery $delivery,
        string $failureMessage,
        ?int $responseStatus,
        ?string $responseBody,
        bool $retryable,
        ?int $durationMs = null,
    ): WebhookDelivery {
        $attemptCount = (int) $delivery->attempt_count;
        $shouldRetry = $retryable && $attemptCount < self::MAX_ATTEMPTS;
        $nextRetryAt = $shouldRetry
            ? now()->addSeconds($this->backoffSeconds($attemptCount))
            : null;

        $delivery->update([
            'status' => $shouldRetry ? WebhookDelivery::STATUS_FAILED : WebhookDelivery::STATUS_DEAD,
            'response_status' => $responseStatus,
            'duration_ms' => $durationMs,
            'response_body_excerpt' => $this->excerpt($responseBody),
            'failure_message' => $this->excerpt($failureMessage, 1000),
            'next_retry_at' => $nextRetryAt,
            'delivered_at' => null,
            'dead_lettered_at' => $shouldRetry ? null : now(),
        ]);

        if ($delivery->endpoint) {
            $delivery->endpoint->update([
                'last_delivery_at' => now(),
                'last_failure_at' => now(),
                'consecutive_failure_count' => ((int) $delivery->endpoint->consecutive_failure_count) + 1,
            ]);
        }

        if ($shouldRetry) {
            $this->dispatchDelivery((string) $delivery->id, $nextRetryAt);
        }

        return $delivery->fresh(['endpoint', 'integrationEvent']) ?? $delivery;
    }

    private function dispatchDelivery(string $deliveryId, ?CarbonInterface $delayUntil = null): void
    {
        if (config('queue.default') === 'sync') {
            if (! $delayUntil || $delayUntil->isPast()) {
                $this->deliverById($deliveryId);
            }

            return;
        }

        $job = DeliverWebhook::dispatch($deliveryId);

        if ($delayUntil) {
            $job->delay($delayUntil);
        }
    }

    private function backoffSeconds(int $attemptCount): int
    {
        $index = max(0, min(count(self::BACKOFF_SECONDS) - 1, $attemptCount - 1));

        return self::BACKOFF_SECONDS[$index];
    }

    private function excerpt(?string $value, int $limit = 2000): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (strlen($trimmed) <= $limit) {
            return $trimmed;
        }

        return substr($trimmed, 0, $limit - 3).'...';
    }
}
