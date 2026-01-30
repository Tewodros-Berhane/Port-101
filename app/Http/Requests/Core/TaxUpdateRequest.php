<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;
        $taxId = $this->route('tax')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('taxes', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($taxId),
            ],
            'type' => ['required', 'string', Rule::in(['percent', 'fixed'])],
            'rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
