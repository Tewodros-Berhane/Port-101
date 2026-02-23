<?php

namespace App\Modules\Purchasing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseReceiptCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $orderId,
        public readonly string $companyId,
    ) {}
}
