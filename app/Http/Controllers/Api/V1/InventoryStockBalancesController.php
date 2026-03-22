<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStockBalancesController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InventoryStockLevel::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $productId = trim((string) $request->input('product_id', ''));
        $locationId = trim((string) $request->input('location_id', ''));
        $warehouseId = trim((string) $request->input('warehouse_id', ''));
        $locationType = trim((string) $request->input('location_type', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['updated_at', 'created_at', 'on_hand_quantity', 'reserved_quantity'],
            defaultSort: 'updated_at',
            defaultDirection: 'desc',
        );

        $levels = InventoryStockLevel::query()
            ->with([
                'product:id,name,sku',
                'location:id,warehouse_id,code,name,type',
                'location.warehouse:id,code,name',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->whereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    })->orWhereHas('location', function ($locationQuery) use ($search) {
                        $locationQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhereHas('warehouse', fn ($warehouseQuery) => $warehouseQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%"));
                    });
                });
            })
            ->when($productId !== '', fn ($query) => $query->where('product_id', $productId))
            ->when($locationId !== '', fn ($query) => $query->where('location_id', $locationId))
            ->when($warehouseId !== '', fn ($query) => $query->whereHas('location', fn ($locationQuery) => $locationQuery
                ->where('warehouse_id', $warehouseId)))
            ->when($locationType !== '', fn ($query) => $query->whereHas('location', fn ($locationQuery) => $locationQuery
                ->where('type', $locationType)))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $levels,
            data: collect($levels->items())
                ->map(fn (InventoryStockLevel $level) => $this->mapLevel($level, $user))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'product_id' => $productId,
                'location_id' => $locationId,
                'warehouse_id' => $warehouseId,
                'location_type' => $locationType,
            ],
        );
    }

    private function mapLevel(InventoryStockLevel $level, User $user): array
    {
        return [
            'id' => $level->id,
            'product_id' => $level->product_id,
            'product_name' => $level->product?->name,
            'product_sku' => $level->product?->sku,
            'location_id' => $level->location_id,
            'location_code' => $level->location?->code,
            'location_name' => $level->location?->name,
            'location_type' => $level->location?->type,
            'warehouse_id' => $level->location?->warehouse_id,
            'warehouse_code' => $level->location?->warehouse?->code,
            'warehouse_name' => $level->location?->warehouse?->name,
            'on_hand_quantity' => (float) $level->on_hand_quantity,
            'reserved_quantity' => (float) $level->reserved_quantity,
            'available_quantity' => round(
                (float) $level->on_hand_quantity - (float) $level->reserved_quantity,
                4,
            ),
            'updated_at' => $level->updated_at?->toIso8601String(),
            'can_view' => $user->can('view', $level),
        ];
    }
}
