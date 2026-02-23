<?php

namespace App\Http\Controllers\Inventory;

use App\Core\Inventory\Models\InventoryLocation;
use App\Core\Inventory\Models\InventoryWarehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryLocationStoreRequest;
use App\Http\Requests\Inventory\InventoryLocationUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryLocationsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryLocation::class);

        $user = $request->user();

        $query = InventoryLocation::query()
            ->with('warehouse:id,name')
            ->orderBy('name')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        $locations = $query->paginate(20)->withQueryString();

        return Inertia::render('inventory/locations/index', [
            'locations' => $locations->through(function (InventoryLocation $location) {
                return [
                    'id' => $location->id,
                    'warehouse_name' => $location->warehouse?->name,
                    'code' => $location->code,
                    'name' => $location->name,
                    'type' => $location->type,
                    'is_active' => (bool) $location->is_active,
                ];
            }),
            'locationTypes' => InventoryLocation::TYPES,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InventoryLocation::class);

        return Inertia::render('inventory/locations/create', [
            'warehouses' => $this->warehouseOptions(),
            'locationTypes' => InventoryLocation::TYPES,
        ]);
    }

    public function store(InventoryLocationStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryLocation::class);

        $user = $request->user();

        $location = InventoryLocation::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('company.inventory.locations.edit', $location)
            ->with('success', 'Location created.');
    }

    public function edit(InventoryLocation $location): Response
    {
        $this->authorize('view', $location);

        return Inertia::render('inventory/locations/edit', [
            'location' => [
                'id' => $location->id,
                'warehouse_id' => $location->warehouse_id,
                'code' => $location->code,
                'name' => $location->name,
                'type' => $location->type,
                'is_active' => (bool) $location->is_active,
            ],
            'warehouses' => $this->warehouseOptions(),
            'locationTypes' => InventoryLocation::TYPES,
        ]);
    }

    public function update(
        InventoryLocationUpdateRequest $request,
        InventoryLocation $location
    ): RedirectResponse {
        $this->authorize('update', $location);

        $location->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('company.inventory.locations.edit', $location)
            ->with('success', 'Location updated.');
    }

    public function destroy(InventoryLocation $location): RedirectResponse
    {
        $this->authorize('delete', $location);

        $linkedLevels = $location->stockLevels()->exists();
        $linkedMoves = $location->sourceMoves()->exists() || $location->destinationMoves()->exists();

        if ($linkedLevels || $linkedMoves) {
            return back()->with('error', 'Location is referenced by stock records or moves and cannot be deleted.');
        }

        $location->delete();

        return redirect()
            ->route('company.inventory.locations.index')
            ->with('success', 'Location removed.');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function warehouseOptions(): array
    {
        return InventoryWarehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (InventoryWarehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
            ])
            ->values()
            ->all();
    }
}
