<?php

namespace App\Modules\Integrations;

use App\Modules\Integrations\Models\WebhookEndpoint;

class WebhookSignatureService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{headers: array<string, string>, raw_body: string}
     */
    public function signedPayload(
        WebhookEndpoint $endpoint,
        string $eventId,
        string $eventType,
        array $payload,
    ): array {
        $timestamp = now()->toIso8601String();
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($rawBody)) {
            $rawBody = '{}';
        }

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$rawBody,
            (string) $endpoint->signing_secret,
        );

        return [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Port-101-Webhooks/1.0',
                'X-Port101-Event' => $eventType,
                'X-Port101-Event-Id' => $eventId,
                'X-Port101-Timestamp' => $timestamp,
                'X-Port101-Signature' => $signature,
            ],
            'raw_body' => $rawBody,
        ];
    }
}
