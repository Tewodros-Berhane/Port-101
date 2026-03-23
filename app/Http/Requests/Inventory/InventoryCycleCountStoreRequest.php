<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryCycleCountStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'uuid', $this->companyScopedExists('inventory_warehouses')],
            'location_id' => ['nullable', 'uuid', $this->companyScopedExists('inventory_locations')],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['uuid', $this->companyScopedExists('products')],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $warehouseId = $this->input('warehouse_id');
            $locationId = $this->input('location_id');

            if (! $warehouseId && ! $locationId) {
                $validator->errors()->add('location_id', 'Cycle counts require a warehouse or location scope.');
            }

            if ($warehouseId && $locationId) {
                $locationWarehouseId = \App\Modules\Inventory\Models\InventoryLocation::query()
                    ->where('company_id', $this->user()?->current_company_id)
                    ->where('id', $locationId)
                    ->value('warehouse_id');

                if ($locationWarehouseId && (string) $locationWarehouseId !== (string) $warehouseId) {
                    $validator->errors()->add('location_id', 'Selected location does not belong to the selected warehouse.');
                }
            }

            $productIds = collect($this->input('product_ids', []))
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->values();

            if ($productIds->isEmpty()) {
                return;
            }

            $invalidProducts = \App\Core\MasterData\Models\Product::query()
                ->where('company_id', $this->user()?->current_company_id)
                ->whereIn('id', $productIds->all())
                ->where('type', '!=', \App\Core\MasterData\Models\Product::TYPE_STOCK)
                ->exists();

            if ($invalidProducts) {
                $validator->errors()->add('product_ids', 'Cycle counts only support stock products.');
            }
        });
    }
}
