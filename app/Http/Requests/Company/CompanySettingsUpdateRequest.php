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
            'tax_period' => ['nullable', 'string', Rule::in(['monthly', 'quarterly', 'annual'])],
            'tax_submission_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'approval_enabled' => ['nullable', 'boolean'],
            'approval_policy' => ['nullable', 'string', Rule::in(['none', 'amount_based', 'always'])],
            'approval_threshold_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'approval_escalation_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'sales_order_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9-]+$/'],
            'sales_order_next_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'purchase_order_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9-]+$/'],
            'purchase_order_next_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'invoice_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z0-9-]+$/'],
            'invoice_next_number' => ['nullable', 'integer', 'min:1', 'max:999999999'],
        ];
    }
}
