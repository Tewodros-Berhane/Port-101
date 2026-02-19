<?php

namespace App\Http\Controllers\Core;

use App\Core\Attachments\Models\Attachment;
use App\Core\MasterData\Models\Product;
use App\Core\MasterData\Models\Tax;
use App\Core\MasterData\Models\Uom;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\ProductStoreRequest;
use App\Http\Requests\Core\ProductUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['uom', 'defaultTax'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('core/products/index', [
            'products' => $products->through(function (Product $product) {
                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'type' => $product->type,
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
        ]);
    }

    public function store(ProductStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $user = $request->user();

        $product = Product::create([
            ...$request->validated(),
            'company_id' => $user?->current_company_id,
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);

        return redirect()
            ->route('core.products.edit', $product)
            ->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

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
                'uom_id' => $product->uom_id,
                'default_tax_id' => $product->default_tax_id,
                'description' => $product->description,
                'is_active' => $product->is_active,
            ],
            'attachments' => $attachments,
            'uoms' => Uom::query()->orderBy('name')->get(['id', 'name']),
            'taxes' => Tax::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $user = $request->user();

        $product->update([
            ...$request->validated(),
            'updated_by' => $user?->id,
        ]);

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
}
