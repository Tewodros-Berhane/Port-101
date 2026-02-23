<?php

namespace App\Http\Controllers\Inventory;

use App\Core\Inventory\Models\InventoryStockLevel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryStockLevelsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryStockLevel::class);

        $user = $request->user();

        $query = InventoryStockLevel::query()
            ->with([
                'product:id,name,sku',
                'location:id,name,type,warehouse_id',
                'location.warehouse:id,name',
            ])
            ->orderByDesc('updated_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $levels = $query->paginate(30)->withQueryString();

        return Inertia::render('inventory/stock-levels/index', [
            'stockLevels' => $levels->through(function (InventoryStockLevel $level) {
                return [
                    'id' => $level->id,
                    'product_name' => $level->product?->name,
                    'product_sku' => $level->product?->sku,
                    'location_name' => $level->location?->name,
                    'location_type' => $level->location?->type,
                    'warehouse_name' => $level->location?->warehouse?->name,
                    'on_hand_quantity' => (float) $level->on_hand_quantity,
                    'reserved_quantity' => (float) $level->reserved_quantity,
                    'available_quantity' => round(
                        (float) $level->on_hand_quantity - (float) $level->reserved_quantity,
                        4,
                    ),
                    'updated_at' => $level->updated_at?->toIso8601String(),
                ];
            }),
        ]);
    }
}
