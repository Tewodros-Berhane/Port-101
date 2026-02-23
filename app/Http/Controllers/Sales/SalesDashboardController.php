<?php

namespace App\Http\Controllers\Sales;

use App\Modules\Sales\Models\SalesLead;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->hasPermission('sales.leads.view')
                || $user?->hasPermission('sales.quotes.view')
                || $user?->hasPermission('sales.orders.view'),
            403
        );

        $leadQuery = SalesLead::query();
        $quoteQuery = SalesQuote::query();
        $orderQuery = SalesOrder::query();

        if ($user) {
            $leadQuery = $user->applyDataScopeToQuery($leadQuery);
            $quoteQuery = $user->applyDataScopeToQuery($quoteQuery);
            $orderQuery = $user->applyDataScopeToQuery($orderQuery);
        }

        $leadCounts = [
            'new' => (clone $leadQuery)->where('stage', 'new')->count(),
            'qualified' => (clone $leadQuery)->where('stage', 'qualified')->count(),
            'quoted' => (clone $leadQuery)->where('stage', 'quoted')->count(),
            'won' => (clone $leadQuery)->where('stage', 'won')->count(),
            'lost' => (clone $leadQuery)->where('stage', 'lost')->count(),
            'total' => (clone $leadQuery)->count(),
        ];

        $quoteCounts = [
            'draft' => (clone $quoteQuery)->where('status', SalesQuote::STATUS_DRAFT)->count(),
            'sent' => (clone $quoteQuery)->where('status', SalesQuote::STATUS_SENT)->count(),
            'approved' => (clone $quoteQuery)->where('status', SalesQuote::STATUS_APPROVED)->count(),
            'confirmed' => (clone $quoteQuery)->where('status', SalesQuote::STATUS_CONFIRMED)->count(),
            'total' => (clone $quoteQuery)->count(),
        ];

        $orderCounts = [
            'draft' => (clone $orderQuery)->where('status', SalesOrder::STATUS_DRAFT)->count(),
            'confirmed' => (clone $orderQuery)->where('status', SalesOrder::STATUS_CONFIRMED)->count(),
            'fulfilled' => (clone $orderQuery)->where('status', SalesOrder::STATUS_FULFILLED)->count(),
            'invoiced' => (clone $orderQuery)->where('status', SalesOrder::STATUS_INVOICED)->count(),
            'total' => (clone $orderQuery)->count(),
        ];

        $pipelineValue = (float) (clone $quoteQuery)
            ->whereIn('status', [
                SalesQuote::STATUS_DRAFT,
                SalesQuote::STATUS_SENT,
                SalesQuote::STATUS_APPROVED,
            ])
            ->sum('grand_total');

        return Inertia::render('sales/index', [
            'leadCounts' => $leadCounts,
            'quoteCounts' => $quoteCounts,
            'orderCounts' => $orderCounts,
            'pipelineValue' => round($pipelineValue, 2),
        ]);
    }
}


