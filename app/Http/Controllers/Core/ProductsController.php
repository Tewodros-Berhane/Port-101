<?php

namespace App\Http\Controllers\Core;

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\ProductStoreRequest;
use App\Http\Requests\Core\ProductUpdateRequest;
use App\Modules\Inventory\InventoryBundleService;
use App\Modules\Inventory\Models\ProductBundle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $user = $request->user();

        $productsQuery = Product::query()
            ->with(['uom', 'defaultTax', 'bundle'])
            ->orderBy('name')
            ->when($user, fn ($query) => $user->applyDataScopeToQuery($query));

        $products = $productsQuery
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/products/index', [
            'products' => $products->through(function (Product $product) {
                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'type' => $product->type,
                    'tracking_mode' => $product->tracking_mode,
                    'bundle_mode' => $product->bundle?->mode,
                    'uom' => $product->uom?->name,
                    'tax' => $product->defaultTax?->name,
                    'is_active' => $product->is_active,
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('core/products/create', [
            'uoms' => Uom::query()->orderBy('name')->get(['id', 'name']),
            'taxes' => Tax::query()->orderBy('name')->get(['id', 'name']),
            'trackingModes' => Product::TRACKING_MODES,
            'bundleModes' => ProductBundle::MODES,
            'bundleProducts' => $this->bundleProductOptions(),
        ]);
    }

    public function store(
        ProductStoreRequest $request,
        InventoryBundleService $bundleService,
    ): RedirectResponse {
        $this->authorize('create', Product::class);

        $user = $request->user();

        $product = Product::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        $bundleService->syncProductBundle(
            product: $product,
            payload: $request->validated('bundle'),
            actorId: $user?->id,
        );

        return redirect()
            ->route('core.products.edit', $product)
            ->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);
        $product->loadMissing('bundle.components.componentProduct');

        $attachments = Attachment::query()
            ->where('attachable_type', $product::class)
            ->where('attachable_id', $product->id)
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

        return Inertia::render('core/products/edit', [
            'product' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type,
                'tracking_mode' => $product->tracking_mode,
                'uom_id' => $product->uom_id,
                'default_tax_id' => $product->default_tax_id,
                'description' => $product->description,
                'is_active' => $product->is_active,
                'bundle' => [
                    'enabled' => $product->bundle !== null && ! $product->bundle->trashed() && $product->bundle->is_active,
                    'mode' => $product->bundle?->mode ?? ProductBundle::MODE_SALES_ONLY,
                    'components' => $product->bundle?->components
                        ->map(fn ($component) => [
                            'product_id' => $component->component_product_id,
                            'quantity' => (float) $component->quantity,
                            'product_name' => $component->componentProduct?->name,
                            'product_sku' => $component->componentProduct?->sku,
                        ])
                        ->values()
                        ->all() ?? [],
                ],
            ],
            'attachments' => $attachments,
            'uoms' => Uom::query()->orderBy('name')->get(['id', 'name']),
            'taxes' => Tax::query()->orderBy('name')->get(['id', 'name']),
            'trackingModes' => Product::TRACKING_MODES,
            'bundleModes' => ProductBundle::MODES,
            'bundleProducts' => $this->bundleProductOptions($product->id),
        ]);
    }

    public function update(
        ProductUpdateRequest $request,
        Product $product,
        InventoryBundleService $bundleService,
    ): RedirectResponse {
        $this->authorize('update', $product);

        $user = $request->user();

        $product->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

        $bundleService->syncProductBundle(
            product: $product->fresh(),
            payload: $request->validated('bundle'),
            actorId: $user?->id,
        );

        return redirect()
            ->route('core.products.edit', $product)
            ->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('core.products.index')
            ->with('success', 'Product removed.');
    }

    /**
     * @return array<int, array{id: string, name: string, sku: string|null}>
     */
    private function bundleProductOptions(?string $exceptProductId = null): array
    {
        return Product::query()
            ->where('type', Product::TYPE_STOCK)
            ->where('is_active', true)
            ->when($exceptProductId, fn ($query) => $query->where('id', '!=', $exceptProductId))
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ])
            ->values()
            ->all();
    }
}
