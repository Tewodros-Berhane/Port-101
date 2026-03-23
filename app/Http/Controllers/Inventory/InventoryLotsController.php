<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\InventoryLot;
use App\Modules\Inventory\Models\InventoryStockMoveLine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryLotsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryLot::class);

        $user = $request->user();
        $search = trim((string) $request->string('search', ''));

        $lots = InventoryLot::query()
            ->with([
                'product:id,name,sku,tracking_mode',
                'location:id,name,type',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('code', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('updated_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder))
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('inventory/lots/index', [
            'filters' => [
                'search' => $search,
            ],
            'lots' => $lots->through(fn (InventoryLot $lot) => [
                'id' => $lot->id,
                'code' => $lot->code,
                'tracking_mode' => $lot->tracking_mode,
                'product_name' => $lot->product?->name,
                'product_sku' => $lot->product?->sku,
                'location_name' => $lot->location?->name,
                'location_type' => $lot->location?->type,
                'quantity_on_hand' => (float) $lot->quantity_on_hand,
                'quantity_reserved' => (float) $lot->quantity_reserved,
                'available_quantity' => round((float) $lot->quantity_on_hand - (float) $lot->quantity_reserved, 4),
                'received_at' => $lot->received_at?->toIso8601String(),
                'last_moved_at' => $lot->last_moved_at?->toIso8601String(),
            ]),
        ]);
    }

    public function show(InventoryLot $lot): Response
    {
        $this->authorize('view', $lot);
        $lot->loadMissing([
            'product:id,name,sku,tracking_mode',
            'location:id,name,type',
        ]);

        $history = InventoryStockMoveLine::query()
            ->with([
                'move.product:id,name,sku',
                'move.sourceLocation:id,name',
                'move.destinationLocation:id,name',
            ])
            ->where(function ($query) use ($lot) {
                $query->where('source_lot_id', $lot->id)
                    ->orWhere('resulting_lot_id', $lot->id);
            })
            ->latest('created_at')
            ->get()
            ->map(fn (InventoryStockMoveLine $line) => [
                'id' => $line->id,
                'move_id' => $line->stock_move_id,
                'reference' => $line->move?->reference,
                'move_type' => $line->move?->move_type,
                'status' => $line->move?->status,
                'source_location_name' => $line->move?->sourceLocation?->name,
                'destination_location_name' => $line->move?->destinationLocation?->name,
                'quantity' => (float) $line->quantity,
                'direction' => (string) $line->source_lot_id === (string) $lot->id
                    ? 'out'
                    : 'in',
                'created_at' => $line->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('inventory/lots/show', [
            'lot' => [
                'id' => $lot->id,
                'code' => $lot->code,
                'tracking_mode' => $lot->tracking_mode,
                'product_name' => $lot->product?->name,
                'product_sku' => $lot->product?->sku,
                'location_name' => $lot->location?->name,
                'location_type' => $lot->location?->type,
                'quantity_on_hand' => (float) $lot->quantity_on_hand,
                'quantity_reserved' => (float) $lot->quantity_reserved,
                'available_quantity' => round((float) $lot->quantity_on_hand - (float) $lot->quantity_reserved, 4),
                'received_at' => $lot->received_at?->toIso8601String(),
                'last_moved_at' => $lot->last_moved_at?->toIso8601String(),
            ],
            'history' => $history,
        ]);
    }
}
