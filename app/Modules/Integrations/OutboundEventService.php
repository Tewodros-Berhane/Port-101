<?php

namespace App\Modules\Integrations;

use App\Jobs\FanOutIntegrationEvent;
use App\Modules\Integrations\Models\IntegrationEvent;
use Illuminate\Support\Str;

class OutboundEventService
{
    public function __construct(
        private readonly IntegrationEventPayloadFactory $payloadFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(
        string $companyId,
        string $eventType,
        string $aggregateType,
        ?string $aggregateId,
        array $data,
        ?string $actorId = null,
    ): IntegrationEvent {
        $eventId = (string) Str::uuid();
        $occurredAt = now();

        $event = new IntegrationEvent([
            'company_id' => $companyId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'occurred_at' => $occurredAt,
            'payload' => $this->payloadFactory->make(
                eventType: $eventType,
                companyId: $companyId,
                data: $data,
                eventId: $eventId,
                occurredAt: $occurredAt,
            ),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $event->id = $eventId;
        $event->save();

        FanOutIntegrationEvent::dispatch($event->id);

        return $event;
    }
}
