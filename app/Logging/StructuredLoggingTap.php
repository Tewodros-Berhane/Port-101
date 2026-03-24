<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger;

class StructuredLoggingTap
{
    public function __construct(
        private readonly StructuredLogProcessor $processor,
    ) {}

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonFormatter(
                    JsonFormatter::BATCH_MODE_JSON,
                    true,
                    false,
                    true,
                ));
            }
        }

        $logger->pushProcessor($this->processor);
    }
}
