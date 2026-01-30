<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceListUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;
        $priceListId = $this->route('price_list')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('price_lists', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($priceListId),
            ],
            'currency_id' => ['nullable', 'uuid', 'exists:currencies,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
