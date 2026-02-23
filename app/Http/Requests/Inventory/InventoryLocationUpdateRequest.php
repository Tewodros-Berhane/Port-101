<?php

namespace App\Http\Requests\Inventory;

use App\Modules\Inventory\Models\InventoryLocation;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryLocationUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;
        /** @var InventoryLocation|null $location */
        $location = $this->route('location');

        return [
            'warehouse_id' => ['nullable', 'uuid', $this->companyScopedExists('inventory_warehouses')],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('inventory_locations', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($location?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(InventoryLocation::TYPES)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->input('type');
            $warehouseId = $this->input('warehouse_id');

            if ($type === InventoryLocation::TYPE_INTERNAL && ! $warehouseId) {
                $validator->errors()->add('warehouse_id', 'Internal locations require a warehouse.');
            }
        });
    }
}


