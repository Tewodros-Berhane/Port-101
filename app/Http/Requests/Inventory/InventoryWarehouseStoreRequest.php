<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryWarehouseStoreRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('inventory_warehouses', 'code')->where('company_id', $companyId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
