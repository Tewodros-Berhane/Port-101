<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\MasterData\Models\Partner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\PartnerStoreRequest;
use App\Http\Requests\Core\PartnerUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Partner::class);

        $perPage = min((int) $request->integer('per_page', 20), 100);
        $search = trim((string) $request->input('search', ''));

        $partners = Partner::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $partners->items(),
            'meta' => [
                'current_page' => $partners->currentPage(),
                'last_page' => $partners->lastPage(),
                'per_page' => $partners->perPage(),
                'total' => $partners->total(),
            ],
        ]);
    }

    public function store(PartnerStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Partner::class);

        $user = $request->user();

        $partner = Partner::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return response()->json([
            'data' => $partner,
        ], 201);
    }

    public function show(Partner $partner): JsonResponse
    {
        $this->authorize('view', $partner);

        return response()->json([
            'data' => $partner,
        ]);
    }

    public function update(PartnerUpdateRequest $request, Partner $partner): JsonResponse
    {
        $this->authorize('update', $partner);

        $partner->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'data' => $partner->fresh(),
        ]);
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $this->authorize('delete', $partner);

        $partner->delete();

        return response()->json(status: 204);
    }
}

