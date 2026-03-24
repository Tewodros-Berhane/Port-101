<?php

namespace App\Http\Requests\Inventory;

use App\Core\MasterData\Models\Product;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryReorderRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryReorderRuleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;

        return [
            'product_id' => [
                'required',
                'string',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->where('type', Product::TYPE_STOCK)
                        ->where('is_active', true)),
            ],
            'location_id' => [
                'required',
                'string',
                Rule::exists('inventory_locations', 'id')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->where('type', InventoryLocation::TYPE_INTERNAL)
                        ->where('is_active', true)),
            ],
            'preferred_vendor_id' => [
                'nullable',
                'string',
                Rule::exists('partners', 'id')
                    ->where(fn ($query) => $query
                        ->where('company_id', $companyId)
                        ->whereIn('type', ['vendor', 'both'])
                        ->where('is_active', true)),
            ],
            'min_quantity' => ['required', 'numeric', 'min:0'],
            'max_quantity' => ['required', 'numeric', 'gt:0'],
            'reorder_quantity' => ['nullable', 'numeric', 'gt:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $companyId = (string) $this->user()?->current_company_id;
                $min = $this->input('min_quantity');
                $max = $this->input('max_quantity');
                $productId = (string) $this->input('product_id');
                $locationId = (string) $this->input('location_id');
                $currentRuleId = $this->route('rule')?->id;

                if ($min === null || $max === null) {
                    $min = null;
                    $max = null;
                }

                if ($min !== null && $max !== null && (float) $max < (float) $min) {
                    $validator->errors()->add('max_quantity', 'Maximum quantity must be greater than or equal to the minimum quantity.');
                }

                if ($productId === '' || $locationId === '') {
                    return;
                }

                $duplicateRuleExists = InventoryReorderRule::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->where('location_id', $locationId)
                    ->when($currentRuleId, fn ($query) => $query->where('id', '!=', $currentRuleId))
                    ->exists();

                if ($duplicateRuleExists) {
                    $validator->errors()->add('product_id', 'A reordering rule already exists for this product and location.');
                }
            },
        ];
    }
}
