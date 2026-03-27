<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger;

class StructuredLoggingTap
{
    public function __construct(
        private readonly StructuredLogProcessor $processor,
    ) {}

    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Logger) {
            return;
        }

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonFormatter(
                    JsonFormatter::BATCH_MODE_JSON,
                    true,
                    false,
                    true,
                ));
            }
        }

        $monolog->pushProcessor($this->processor);
    }
}
