<?php

namespace App\Http\Controllers\Core;

use App\Core\MasterData\Models\Currency;
use App\Core\MasterData\Models\PriceList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\PriceListStoreRequest;
use App\Http\Requests\Core\PriceListUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceListsController extends Controller
{
    public function index(Request $request): Response
    {
        $priceLists = PriceList::query()
            ->with('currency')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/price-lists/index', [
            'priceLists' => $priceLists->through(function (PriceList $priceList) {
                return [
                    'id' => $priceList->id,
                    'name' => $priceList->name,
                    'currency' => $priceList->currency?->code,
                    'is_active' => $priceList->is_active,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('core/price-lists/create', [
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(PriceListStoreRequest $request): RedirectResponse
    {
        $user = $request->user();

        $priceList = PriceList::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.price-lists.edit', $priceList)
            ->with('success', 'Price list created.');
    }

    public function edit(PriceList $priceList): Response
    {
        return Inertia::render('core/price-lists/edit', [
            'priceList' => [
                'id' => $priceList->id,
                'name' => $priceList->name,
                'currency_id' => $priceList->currency_id,
                'is_active' => $priceList->is_active,
            ],
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(PriceListUpdateRequest $request, PriceList $priceList): RedirectResponse
    {
        $user = $request->user();

        $priceList->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.price-lists.edit', $priceList)
            ->with('success', 'Price list updated.');
    }

    public function destroy(Request $request, PriceList $priceList): RedirectResponse
    {
        $priceList->delete();

        return redirect()
            ->route('core.price-lists.index')
            ->with('success', 'Price list removed.');
    }
}
