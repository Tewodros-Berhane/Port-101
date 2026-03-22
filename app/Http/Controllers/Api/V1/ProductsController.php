<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\MasterData\Models\Product;
use App\Http\Requests\Core\ProductStoreRequest;
use App\Http\Requests\Core\ProductUpdateRequest;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['name', 'sku', 'created_at', 'updated_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $products = Product::query()
            ->with(['uom:id,name', 'defaultTax:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $products,
            data: $products->items(),
            sort: $sort,
            direction: $direction,
            filters: ['search' => $search],
        );
    }

    public function store(ProductStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $user = $request->user();

        $product = Product::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return $this->respond($product->fresh(['uom:id,name', 'defaultTax:id,name']), 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->respond($product->load(['uom:id,name', 'defaultTax:id,name']));
    }

    public function update(ProductUpdateRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return $this->respond($product->fresh(['uom:id,name', 'defaultTax:id,name']));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return $this->respondNoContent();
    }
}
