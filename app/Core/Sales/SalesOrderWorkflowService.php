<?php

namespace App\Core\Sales;

use App\Core\Sales\Events\SalesOrderConfirmed;
use App\Core\Sales\Events\SalesOrderReadyForInvoice;
use App\Core\Sales\Models\SalesOrder;
use App\Models\User;

class SalesOrderWorkflowService
{
    public function confirm(SalesOrder $order, User $actor): SalesOrder
    {
        if ($order->status === SalesOrder::STATUS_CONFIRMED) {
            return $order;
        }

        $order->update([
            'status' => SalesOrder::STATUS_CONFIRMED,
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
            'updated_by' => $actor->id,
        ]);

        event(new SalesOrderConfirmed(
            orderId: $order->id,
            companyId: $order->company_id,
            quoteId: $order->quote_id,
        ));

        event(new SalesOrderReadyForInvoice(
            orderId: $order->id,
            companyId: $order->company_id,
            quoteId: $order->quote_id,
        ));

        return $order->fresh();
    }
}
