<?php

namespace App\Http\Requests\Core;

use App\Core\MasterData\Models\Product;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Inventory\Models\ProductBundle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;

        return [
            'sku' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in([Product::TYPE_STOCK, Product::TYPE_SERVICE])],
            'tracking_mode' => ['nullable', 'string', Rule::in(Product::TRACKING_MODES)],
            'uom_id' => ['nullable', 'uuid', $this->companyScopedExists('uoms')],
            'default_tax_id' => ['nullable', 'uuid', $this->companyScopedExists('taxes')],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'bundle' => ['nullable', 'array'],
            'bundle.enabled' => ['nullable', 'boolean'],
            'bundle.mode' => ['nullable', 'string', Rule::in(ProductBundle::MODES)],
            'bundle.components' => ['nullable', 'array'],
            'bundle.components.*.product_id' => ['nullable', 'uuid', $this->companyScopedExists('products')],
            'bundle.components.*.quantity' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $enabled = $this->boolean('bundle.enabled');
            $components = collect($this->input('bundle.components', []))
                ->filter(fn ($component) => filled($component['product_id'] ?? null) || filled($component['quantity'] ?? null))
                ->values();

            if (
                $this->input('type') === Product::TYPE_SERVICE
                && $this->input('tracking_mode', Product::TRACKING_NONE) !== Product::TRACKING_NONE
            ) {
                $validator->errors()->add('tracking_mode', 'Only stock products can use lot or serial tracking.');
            }

            if (! $enabled) {
                return;
            }

            if ($this->input('type') !== Product::TYPE_STOCK) {
                $validator->errors()->add('bundle.enabled', 'Only stock products can be configured as bundles.');
            }

            if (! filled($this->input('bundle.mode'))) {
                $validator->errors()->add('bundle.mode', 'Bundle mode is required when bundle configuration is enabled.');
            }

            if ($components->isEmpty()) {
                $validator->errors()->add('bundle.components', 'At least one bundle component is required.');

                return;
            }

            $componentIds = $components
                ->pluck('product_id')
                ->filter()
                ->values();

            if ($componentIds->count() !== $componentIds->unique()->count()) {
                $validator->errors()->add('bundle.components', 'Bundle component products must be unique.');
            }

            $products = Product::query()
                ->with('bundle')
                ->whereIn('id', $componentIds)
                ->get()
                ->keyBy('id');

            foreach ($componentIds as $componentId) {
                $componentProduct = $products->get($componentId);

                if (! $componentProduct) {
                    continue;
                }

                if (! $componentProduct->is_active || $componentProduct->type !== Product::TYPE_STOCK) {
                    $validator->errors()->add('bundle.components', 'Bundle components must be active stock products.');
                    break;
                }

                if ($componentProduct->hasActiveBundle()) {
                    $validator->errors()->add('bundle.components', 'Nested bundles are not supported in this release.');
                    break;
                }
            }
        });
    }
}
