<?php

namespace App\Modules\Integrations;

use App\Modules\Integrations\Models\WebhookEndpoint;
use Illuminate\Support\Str;

class WebhookEndpointService
{
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
            'api_version' => 'v1',
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'subscribed_events' => array_values($attributes['subscribed_events'] ?? []),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

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
        $signingSecret = $this->newSigningSecret();

        $endpoint->update([
            'signing_secret' => $signingSecret,
            'updated_by' => $actorId,
        ]);

        return [
            'endpoint' => $endpoint->fresh() ?? $endpoint,
            'signing_secret' => $signingSecret,
        ];
    }

    public function secretPreview(WebhookEndpoint $endpoint): string
    {
        $secret = (string) $endpoint->signing_secret;

        if (strlen($secret) <= 8) {
            return str_repeat('*', max(strlen($secret), 4));
        }

        return substr($secret, 0, 4).'...'.substr($secret, -4);
    }

    private function newSigningSecret(): string
    {
        return Str::random(64);
    }
}
