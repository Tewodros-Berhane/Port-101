<?php

namespace App\Http\Controllers\Inventory;

use App\Modules\Inventory\Models\InventoryWarehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryWarehouseStoreRequest;
use App\Http\Requests\Inventory\InventoryWarehouseUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryWarehousesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryWarehouse::class);

        $user = $request->user();

        $query = InventoryWarehouse::query()
            ->withCount('locations')
            ->orderBy('name')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $warehouses = $query->paginate(20)->withQueryString();

        return Inertia::render('inventory/warehouses/index', [
            'warehouses' => $warehouses->through(function (InventoryWarehouse $warehouse) {
                return [
                    'id' => $warehouse->id,
                    'code' => $warehouse->code,
                    'name' => $warehouse->name,
                    'is_active' => (bool) $warehouse->is_active,
                    'locations_count' => $warehouse->locations_count,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InventoryWarehouse::class);

        return Inertia::render('inventory/warehouses/create');
    }

    public function store(InventoryWarehouseStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryWarehouse::class);

        $user = $request->user();

        $warehouse = InventoryWarehouse::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('company.inventory.warehouses.edit', $warehouse)
            ->with('success', 'Warehouse created.');
    }

    public function edit(InventoryWarehouse $warehouse): Response
    {
        $this->authorize('view', $warehouse);

        return Inertia::render('inventory/warehouses/edit', [
            'warehouse' => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'is_active' => (bool) $warehouse->is_active,
            ],
        ]);
    }

    public function update(
        InventoryWarehouseUpdateRequest $request,
        InventoryWarehouse $warehouse
    ): RedirectResponse {
        $this->authorize('update', $warehouse);

        $warehouse->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.inventory.warehouses.edit', $warehouse)
            ->with('success', 'Warehouse updated.');
    }

    public function destroy(InventoryWarehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        if ($warehouse->locations()->exists()) {
            return back()->with('error', 'Remove warehouse locations before deleting this warehouse.');
        }

        $warehouse->delete();

        return redirect()
            ->route('company.inventory.warehouses.index')
            ->with('success', 'Warehouse removed.');
    }
}


