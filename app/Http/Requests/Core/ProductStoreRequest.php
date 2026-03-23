<?php

namespace App\Http\Requests\Core;

use App\Core\MasterData\Models\Product;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (
                $this->input('type') === Product::TYPE_SERVICE
                && $this->input('tracking_mode', Product::TRACKING_NONE) !== Product::TRACKING_NONE
            ) {
                $validator->errors()->add('tracking_mode', 'Only stock products can use lot or serial tracking.');
            }
        });
    }
}
