<?php

namespace App\Http\Controllers\Core;

use App\Core\MasterData\Models\Currency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CurrencyStoreRequest;
use App\Http\Requests\Core\CurrencyUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrenciesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = Currency::query()
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/currencies/index', [
            'currencies' => $currencies->through(function (Currency $currency) {
                return [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimal_places' => $currency->decimal_places,
                    'is_active' => $currency->is_active,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Currency::class);

        return Inertia::render('core/currencies/create');
    }

    public function store(CurrencyStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Currency::class);

        $user = $request->user();

        $currency = Currency::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.currencies.edit', $currency)
            ->with('success', 'Currency created.');
    }

    public function edit(Currency $currency): Response
    {
        $this->authorize('update', $currency);

        return Inertia::render('core/currencies/edit', [
            'currency' => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimal_places' => $currency->decimal_places,
                'is_active' => $currency->is_active,
            ],
        ]);
    }

    public function update(CurrencyUpdateRequest $request, Currency $currency): RedirectResponse
    {
        $this->authorize('update', $currency);

        $user = $request->user();

        $currency->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.currencies.edit', $currency)
            ->with('success', 'Currency updated.');
    }

    public function destroy(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorize('delete', $currency);

        $currency->delete();

        return redirect()
            ->route('core.currencies.index')
            ->with('success', 'Currency removed.');
    }
}
