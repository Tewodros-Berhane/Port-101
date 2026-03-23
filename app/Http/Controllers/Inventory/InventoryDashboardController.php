<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\InventorySetupService;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryLot;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Inventory\Models\InventoryWarehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryDashboardController extends Controller
{
    public function index(
        Request $request,
        InventorySetupService $setupService,
    ): Response {
        $user = $request->user();

        abort_unless(
            $user?->hasPermission('inventory.stock.view')
                || $user?->hasPermission('inventory.moves.view')
                || $user?->hasPermission('inventory.moves.manage'),
            403
        );

        $companyId = (string) $user?->current_company_id;

        if ($companyId) {
            $setupService->ensureDefaults($companyId, $user?->id);
        }

        $warehouseQuery = InventoryWarehouse::query();
        $locationQuery = InventoryLocation::query();
        $lotQuery = InventoryLot::query();
        $levelQuery = InventoryStockLevel::query();
        $moveQuery = InventoryStockMove::query();

        if ($user) {
            $warehouseQuery = $user->applyDataScopeToQuery($warehouseQuery);
            $locationQuery = $user->applyDataScopeToQuery($locationQuery);
            $lotQuery = $user->applyDataScopeToQuery($lotQuery);
            $levelQuery = $user->applyDataScopeToQuery($levelQuery);
            $moveQuery = $user->applyDataScopeToQuery($moveQuery);
        }

        $recentMoves = (clone $moveQuery)
            ->with([
                'product:id,name',
                'sourceLocation:id,name',
                'destinationLocation:id,name',
            ])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(function (InventoryStockMove $move) {
                return [
                    'id' => $move->id,
                    'reference' => $move->reference,
                    'move_type' => $move->move_type,
                    'status' => $move->status,
                    'product_name' => $move->product?->name,
                    'source_location_name' => $move->sourceLocation?->name,
                    'destination_location_name' => $move->destinationLocation?->name,
                    'quantity' => (float) $move->quantity,
                    'created_at' => $move->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $stockAlerts = (clone $levelQuery)
            ->with([
                'product:id,name',
                'location:id,name,type',
            ])
            ->whereRaw('on_hand_quantity <= reserved_quantity OR on_hand_quantity <= 5')
            ->orderBy('on_hand_quantity')
            ->limit(8)
            ->get()
            ->map(function (InventoryStockLevel $level) {
                return [
                    'id' => $level->id,
                    'product_name' => $level->product?->name,
                    'location_name' => $level->location?->name,
                    'location_type' => $level->location?->type,
                    'on_hand_quantity' => (float) $level->on_hand_quantity,
                    'reserved_quantity' => (float) $level->reserved_quantity,
                    'available_quantity' => round(
                        (float) $level->on_hand_quantity - (float) $level->reserved_quantity,
                        4,
                    ),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('inventory/index', [
            'kpis' => [
                'warehouses' => (clone $warehouseQuery)->count(),
                'locations' => (clone $locationQuery)->count(),
                'tracked_lots' => (clone $lotQuery)->count(),
                'stock_levels' => (clone $levelQuery)->count(),
                'draft_moves' => (clone $moveQuery)
                    ->where('status', InventoryStockMove::STATUS_DRAFT)
                    ->count(),
                'reserved_moves' => (clone $moveQuery)
                    ->where('status', InventoryStockMove::STATUS_RESERVED)
                    ->count(),
                'done_moves_7d' => (clone $moveQuery)
                    ->where('status', InventoryStockMove::STATUS_DONE)
                    ->where('completed_at', '>=', now()->subDays(7))
                    ->count(),
            ],
            'recentMoves' => $recentMoves,
            'stockAlerts' => $stockAlerts,
        ]);
    }
}
