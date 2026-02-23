<?php

namespace App\Core\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockDelivered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $moveId,
        public readonly string $companyId,
        public readonly string $orderId,
        public readonly string $productId,
        public readonly float $quantity,
    ) {}
}
