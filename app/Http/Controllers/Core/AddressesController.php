<?php

namespace App\Http\Controllers\Core;

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Address;
use App\Core\MasterData\Models\Partner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\AddressStoreRequest;
use App\Http\Requests\Core\AddressUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressesController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Address::class);

        $addresses = Address::query()
            ->with('partner')
            ->orderBy('line1')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/addresses/index', [
            'addresses' => $addresses->through(function (Address $address) {
                return [
                    'id' => $address->id,
                    'partner' => $address->partner?->name,
                    'type' => $address->type,
                    'line1' => $address->line1,
                    'city' => $address->city,
                    'state' => $address->state,
                    'country_code' => $address->country_code,
                    'is_primary' => $address->is_primary,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Address::class);

        return Inertia::render('core/addresses/create', [
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(AddressStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Address::class);

        $user = $request->user();

        $address = Address::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.addresses.edit', $address)
            ->with('success', 'Address created.');
    }

    public function edit(Address $address): Response
    {
        $this->authorize('update', $address);

        $attachments = Attachment::query()
            ->where('attachable_type', $address::class)
            ->where('attachable_id', $address->id)
            ->latest('created_at')
            ->get()
            ->map(function (Attachment $attachment) {
                return [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => (int) $attachment->size,
                    'created_at' => $attachment->created_at?->toIso8601String(),
                    'download_url' => route('core.attachments.download', $attachment),
                ];
            });

        return Inertia::render('core/addresses/edit', [
            'address' => [
                'id' => $address->id,
                'partner_id' => $address->partner_id,
                'type' => $address->type,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country_code' => $address->country_code,
                'is_primary' => $address->is_primary,
            ],
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name', 'code']),
            'attachments' => $attachments,
        ]);
    }

    public function update(AddressUpdateRequest $request, Address $address): RedirectResponse
    {
        $this->authorize('update', $address);

        $user = $request->user();

        $address->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.addresses.edit', $address)
            ->with('success', 'Address updated.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('delete', $address);

        $address->delete();

        return redirect()
            ->route('core.addresses.index')
            ->with('success', 'Address removed.');
    }
}
