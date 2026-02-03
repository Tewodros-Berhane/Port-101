<?php

namespace App\Http\Controllers\Core;

use App\Core\MasterData\Models\Uom;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\UomStoreRequest;
use App\Http\Requests\Core\UomUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UomsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Uom::class);

        $uoms = Uom::query()
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/uoms/index', [
            'uoms' => $uoms->through(function (Uom $uom) {
                return [
                    'id' => $uom->id,
                    'name' => $uom->name,
                    'symbol' => $uom->symbol,
                    'is_active' => $uom->is_active,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Uom::class);

        return Inertia::render('core/uoms/create');
    }

    public function store(UomStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Uom::class);

        $user = $request->user();

        $uom = Uom::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.uoms.edit', $uom)
            ->with('success', 'UoM created.');
    }

    public function edit(Uom $uom): Response
    {
        $this->authorize('update', $uom);

        return Inertia::render('core/uoms/edit', [
            'uom' => [
                'id' => $uom->id,
                'name' => $uom->name,
                'symbol' => $uom->symbol,
                'is_active' => $uom->is_active,
            ],
        ]);
    }

    public function update(UomUpdateRequest $request, Uom $uom): RedirectResponse
    {
        $this->authorize('update', $uom);

        $user = $request->user();

        $uom->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.uoms.edit', $uom)
            ->with('success', 'UoM updated.');
    }

    public function destroy(Request $request, Uom $uom): RedirectResponse
    {
        $this->authorize('delete', $uom);

        $uom->delete();

        return redirect()
            ->route('core.uoms.index')
            ->with('success', 'UoM removed.');
    }
}
