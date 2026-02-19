<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;
        $productId = $this->route('product')?->id;

        return [
            'sku' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'sku')
                    ->where('company_id', $companyId)
                    ->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['stock', 'service'])],
            'uom_id' => ['nullable', 'uuid', $this->companyScopedExists('uoms')],
            'default_tax_id' => ['nullable', 'uuid', $this->companyScopedExists('taxes')],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
