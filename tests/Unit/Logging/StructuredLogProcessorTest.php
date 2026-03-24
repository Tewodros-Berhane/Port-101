<?php

use App\Logging\StructuredLogProcessor;
use App\Support\Logging\StructuredLogContext;
use Monolog\Level;
use Monolog\LogRecord;

test('structured log processor appends scoped context into log extra payload', function () {
    $context = new StructuredLogContext;
    $context->setQueueContext([
        'job_name' => 'App\\Jobs\\DeliverWebhook',
        'queue_name' => 'default',
        'module' => 'queue',
        'entity' => 'DeliverWebhook',
        'action' => 'handle',
    ]);

    $processor = new StructuredLogProcessor($context);

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'testing',
        level: Level::Info,
        message: 'Webhook job processed.',
        context: ['webhook_delivery_id' => 'delivery-1'],
        extra: [],
    );

    $processed = $processor($record);

    expect($processed->extra)->toMatchArray([
        'app' => config('app.name'),
        'environment' => app()->environment(),
        'runtime' => 'queue',
        'job_name' => 'App\\Jobs\\DeliverWebhook',
        'queue_name' => 'default',
        'module' => 'queue',
        'entity' => 'DeliverWebhook',
        'action' => 'handle',
    ]);

    expect($processed->context)->toMatchArray([
        'webhook_delivery_id' => 'delivery-1',
    ]);
});
