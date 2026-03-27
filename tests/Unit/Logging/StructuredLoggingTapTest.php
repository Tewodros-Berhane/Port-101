<?php

use App\Logging\StructuredLogProcessor;
use App\Logging\StructuredLoggingTap;
use App\Support\Logging\StructuredLogContext;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

test('structured logging tap configures the wrapped monolog logger', function () {
    $stream = fopen('php://memory', 'w+');
    $monolog = new Logger('testing');
    $handler = new StreamHandler($stream);
    $monolog->pushHandler($handler);

    $illuminateLogger = new IlluminateLogger($monolog);
    $tap = new StructuredLoggingTap(new StructuredLogProcessor(new StructuredLogContext));

    $tap($illuminateLogger);

    expect($handler->getFormatter())->toBeInstanceOf(\Monolog\Formatter\JsonFormatter::class)
        ->and($monolog->getProcessors())->not->toBeEmpty();
});
