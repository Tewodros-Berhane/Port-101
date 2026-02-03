<?php

namespace App\Http\Controllers\Core;

use App\Core\MasterData\Models\Partner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\PartnerStoreRequest;
use App\Http\Requests\Core\PartnerUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnersController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Partner::class);

        $partners = Partner::query()
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/partners/index', [
            'partners' => $partners->through(function (Partner $partner) {
                return [
                    'id' => $partner->id,
                    'code' => $partner->code,
                    'name' => $partner->name,
                    'type' => $partner->type,
                    'email' => $partner->email,
                    'phone' => $partner->phone,
                    'is_active' => $partner->is_active,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Partner::class);

        return Inertia::render('core/partners/create');
    }

    public function store(PartnerStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Partner::class);

        $user = $request->user();

        $partner = Partner::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.partners.edit', $partner)
            ->with('success', 'Partner created.');
    }

    public function edit(Partner $partner): Response
    {
        $this->authorize('update', $partner);

        return Inertia::render('core/partners/edit', [
            'partner' => [
                'id' => $partner->id,
                'code' => $partner->code,
                'name' => $partner->name,
                'type' => $partner->type,
                'email' => $partner->email,
                'phone' => $partner->phone,
                'is_active' => $partner->is_active,
            ],
        ]);
    }

    public function update(PartnerUpdateRequest $request, Partner $partner): RedirectResponse
    {
        $this->authorize('update', $partner);

        $user = $request->user();

        $partner->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.partners.edit', $partner)
            ->with('success', 'Partner updated.');
    }

    public function destroy(Request $request, Partner $partner): RedirectResponse
    {
        $this->authorize('delete', $partner);

        $partner->delete();

        return redirect()
            ->route('core.partners.index')
            ->with('success', 'Partner removed.');
    }
}
