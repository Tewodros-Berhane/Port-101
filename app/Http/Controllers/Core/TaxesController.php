<?php

namespace App\Http\Controllers\Core;

use App\Core\MasterData\Models\Tax;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\TaxStoreRequest;
use App\Http\Requests\Core\TaxUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tax::class);

        $taxes = Tax::query()
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/taxes/index', [
            'taxes' => $taxes->through(function (Tax $tax) {
                return [
                    'id' => $tax->id,
                    'name' => $tax->name,
                    'type' => $tax->type,
                    'rate' => $tax->rate,
                    'is_active' => $tax->is_active,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Tax::class);

        return Inertia::render('core/taxes/create');
    }

    public function store(TaxStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Tax::class);

        $user = $request->user();

        $tax = Tax::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.taxes.edit', $tax)
            ->with('success', 'Tax created.');
    }

    public function edit(Tax $tax): Response
    {
        $this->authorize('update', $tax);

        return Inertia::render('core/taxes/edit', [
            'tax' => [
                'id' => $tax->id,
                'name' => $tax->name,
                'type' => $tax->type,
                'rate' => $tax->rate,
                'is_active' => $tax->is_active,
            ],
        ]);
    }

    public function update(TaxUpdateRequest $request, Tax $tax): RedirectResponse
    {
        $this->authorize('update', $tax);

        $user = $request->user();

        $tax->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.taxes.edit', $tax)
            ->with('success', 'Tax updated.');
    }

    public function destroy(Request $request, Tax $tax): RedirectResponse
    {
        $this->authorize('delete', $tax);

        $tax->delete();

        return redirect()
            ->route('core.taxes.index')
            ->with('success', 'Tax removed.');
    }
}
