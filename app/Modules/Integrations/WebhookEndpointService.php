<?php

namespace App\Modules\Integrations;

use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Models\WebhookSecretRotation;
use Illuminate\Support\Str;

class WebhookEndpointService
{
    public const SIGNATURE_VERSION = 'v1';

    public const SIGNATURE_ALGORITHM = 'hmac-sha256';

    public const REPLAY_WINDOW_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{endpoint: WebhookEndpoint, signing_secret: string}
     */
    public function create(string $companyId, array $attributes, ?string $actorId = null): array
    {
        $signingSecret = $this->newSigningSecret();

        $endpoint = WebhookEndpoint::create([
            'company_id' => $companyId,
            'name' => trim((string) $attributes['name']),
            'target_url' => trim((string) $attributes['target_url']),
            'signing_secret' => $signingSecret,
            'signing_secret_version' => 1,
            'api_version' => 'v1',
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'subscribed_events' => array_values($attributes['subscribed_events'] ?? []),
            'secret_rotated_at' => now(),
            'consecutive_failure_count' => 0,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        $this->recordSecretRotation(
            endpoint: $endpoint,
            currentSecret: $signingSecret,
            actorId: $actorId,
            reason: 'created',
            secretVersion: 1,
        );

        return [
            'endpoint' => $endpoint,
            'signing_secret' => $signingSecret,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(WebhookEndpoint $endpoint, array $attributes, ?string $actorId = null): WebhookEndpoint
    {
        $endpoint->update([
            'name' => trim((string) $attributes['name']),
            'target_url' => trim((string) $attributes['target_url']),
            'is_active' => (bool) ($attributes['is_active'] ?? $endpoint->is_active),
            'subscribed_events' => array_values($attributes['subscribed_events'] ?? []),
            'updated_by' => $actorId,
        ]);

        return $endpoint->fresh() ?? $endpoint;
    }

    /**
     * @return array{endpoint: WebhookEndpoint, signing_secret: string}
     */
    public function rotateSecret(WebhookEndpoint $endpoint, ?string $actorId = null): array
    {
        $previousSecret = (string) $endpoint->signing_secret;
        $signingSecret = $this->newSigningSecret();
        $nextVersion = ((int) $endpoint->signing_secret_version) + 1;

        $endpoint->update([
            'signing_secret' => $signingSecret,
            'signing_secret_version' => $nextVersion,
            'secret_rotated_at' => now(),
            'updated_by' => $actorId,
        ]);

        $this->recordSecretRotation(
            endpoint: $endpoint->fresh() ?? $endpoint,
            currentSecret: $signingSecret,
            actorId: $actorId,
            reason: 'manual',
            secretVersion: $nextVersion,
            previousSecret: $previousSecret,
        );

        return [
            'endpoint' => $endpoint->fresh() ?? $endpoint,
            'signing_secret' => $signingSecret,
        ];
    }

    public function secretPreview(WebhookEndpoint $endpoint): string
    {
        return $this->secretPreviewForValue((string) $endpoint->signing_secret);
    }

    /**
     * @return array<string, mixed>
     */
    public function deliverySecurityPolicy(): array
    {
        return [
            'signature_version' => self::SIGNATURE_VERSION,
            'signature_algorithm' => self::SIGNATURE_ALGORITHM,
            'signed_content' => 'timestamp.raw_body',
            'timestamp_header' => 'X-Port101-Timestamp',
            'signature_header' => 'X-Port101-Signature',
            'signature_version_header' => 'X-Port101-Signature-Version',
            'event_header' => 'X-Port101-Event',
            'event_id_header' => 'X-Port101-Event-Id',
            'replay_window_seconds' => self::REPLAY_WINDOW_SECONDS,
            'consumer_guidance' => [
                'Validate the HMAC over timestamp + "." + raw_body.',
                'Reject payloads older than the replay window.',
                'Deduplicate deliveries by event_id because delivery is at-least-once.',
            ],
        ];
    }

    public function secretPreviewForValue(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return str_repeat('*', max(strlen($secret), 4));
        }

        return substr($secret, 0, 4).'...'.substr($secret, -4);
    }

    public function secretFingerprint(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private function recordSecretRotation(
        WebhookEndpoint $endpoint,
        string $currentSecret,
        ?string $actorId,
        string $reason,
        int $secretVersion,
        ?string $previousSecret = null,
    ): void {
        WebhookSecretRotation::create([
            'company_id' => (string) $endpoint->company_id,
            'webhook_endpoint_id' => (string) $endpoint->id,
            'secret_version' => $secretVersion,
            'reason' => $reason,
            'previous_secret_preview' => $previousSecret
                ? $this->secretPreviewForValue($previousSecret)
                : null,
            'previous_secret_fingerprint' => $previousSecret
                ? $this->secretFingerprint($previousSecret)
                : null,
            'current_secret_preview' => $this->secretPreviewForValue($currentSecret),
            'current_secret_fingerprint' => $this->secretFingerprint($currentSecret),
            'rotated_at' => now(),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    private function newSigningSecret(): string
    {
        return Str::random(64);
    }
}
