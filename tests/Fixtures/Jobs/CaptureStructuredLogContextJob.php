<?php

namespace Tests\Fixtures\Jobs;

use App\Support\Logging\StructuredLogContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CaptureStructuredLogContextJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public static array $capturedContext = [];

    public function handle(StructuredLogContext $context): void
    {
        self::$capturedContext = $context->all();
    }
}
