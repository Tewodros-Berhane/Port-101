<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRfq;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchasingDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->hasPermission('purchasing.rfq.view')
                || $user?->hasPermission('purchasing.po.view'),
            403
        );

        $rfqQuery = PurchaseRfq::query();
        $orderQuery = PurchaseOrder::query();

        if ($user) {
            $rfqQuery = $user->applyDataScopeToQuery($rfqQuery);
            $orderQuery = $user->applyDataScopeToQuery($orderQuery);
        }

        $recentRfqs = (clone $rfqQuery)
            ->with(['partner:id,name', 'order:id,rfq_id,order_number'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (PurchaseRfq $rfq) => [
                'id' => $rfq->id,
                'rfq_number' => $rfq->rfq_number,
                'status' => $rfq->status,
                'partner_name' => $rfq->partner?->name,
                'rfq_date' => $rfq->rfq_date?->toDateString(),
                'grand_total' => (float) $rfq->grand_total,
                'order_number' => $rfq->order?->order_number,
            ])
            ->values()
            ->all();

        $recentOrders = (clone $orderQuery)
            ->with(['partner:id,name', 'rfq:id,rfq_number'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'partner_name' => $order->partner?->name,
                'rfq_number' => $order->rfq?->rfq_number,
                'order_date' => $order->order_date?->toDateString(),
                'grand_total' => (float) $order->grand_total,
            ])
            ->values()
            ->all();

        return Inertia::render('purchasing/index', [
            'kpis' => [
                'draft_rfqs' => (clone $rfqQuery)->where('status', PurchaseRfq::STATUS_DRAFT)->count(),
                'open_rfqs' => (clone $rfqQuery)->whereIn('status', [
                    PurchaseRfq::STATUS_DRAFT,
                    PurchaseRfq::STATUS_SENT,
                    PurchaseRfq::STATUS_VENDOR_RESPONDED,
                ])->count(),
                'selected_rfqs' => (clone $rfqQuery)->where('status', PurchaseRfq::STATUS_SELECTED)->count(),
                'draft_orders' => (clone $orderQuery)->where('status', PurchaseOrder::STATUS_DRAFT)->count(),
                'ordered_orders' => (clone $orderQuery)->whereIn('status', [
                    PurchaseOrder::STATUS_ORDERED,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                ])->count(),
                'received_orders' => (clone $orderQuery)->whereIn('status', [
                    PurchaseOrder::STATUS_RECEIVED,
                    PurchaseOrder::STATUS_BILLED,
                    PurchaseOrder::STATUS_CLOSED,
                ])->count(),
                'open_commitments' => round((float) (clone $orderQuery)
                    ->whereIn('status', [
                        PurchaseOrder::STATUS_DRAFT,
                        PurchaseOrder::STATUS_APPROVED,
                        PurchaseOrder::STATUS_ORDERED,
                        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    ])
                    ->sum('grand_total'), 2),
            ],
            'recentRfqs' => $recentRfqs,
            'recentOrders' => $recentOrders,
        ]);
    }
}
