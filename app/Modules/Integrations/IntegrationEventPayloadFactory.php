<?php

namespace App\Modules\Integrations;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class IntegrationEventPayloadFactory
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function make(
        string $eventType,
        string $companyId,
        array $data,
        ?string $eventId = null,
        ?CarbonInterface $occurredAt = null,
    ): array {
        $occurredAt ??= now();

        return [
            'event_id' => $eventId ?: (string) Str::uuid(),
            'event_type' => $eventType,
            'api_version' => 'v1',
            'occurred_at' => $occurredAt->toIso8601String(),
            'company_id' => $companyId,
            'data' => $data,
        ];
    }
}
