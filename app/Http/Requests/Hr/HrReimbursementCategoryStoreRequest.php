<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class HrReimbursementCategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
            'default_expense_account_reference' => ['nullable', 'string', 'max:255'],
            'requires_receipt' => ['required', 'boolean'],
            'is_project_rebillable' => ['required', 'boolean'],
        ];
    }
}
