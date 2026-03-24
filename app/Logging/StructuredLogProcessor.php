<?php

namespace App\Logging;

use App\Support\Logging\StructuredLogContext;
use Monolog\LogRecord;

class StructuredLogProcessor
{
    public function __construct(
        private readonly StructuredLogContext $context,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: [
            ...$record->extra,
            ...$this->context->all(),
        ]);
    }
}
