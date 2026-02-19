<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanySettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:255'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'fiscal_year_start' => ['nullable', 'date_format:Y-m-d'],
            'locale' => ['nullable', 'string', 'max:10'],
            'date_format' => ['nullable', 'string', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y'])],
            'number_format' => ['nullable', 'string', Rule::in(['1,234.56', '1.234,56', '1 234,56'])],
            'audit_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
