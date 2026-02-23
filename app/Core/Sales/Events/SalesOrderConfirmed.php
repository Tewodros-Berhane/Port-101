<?php

namespace App\Core\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesOrderConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $orderId,
        public readonly string $companyId,
        public readonly ?string $quoteId = null,
    ) {}
}
