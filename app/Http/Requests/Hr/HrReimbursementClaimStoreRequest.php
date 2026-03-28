<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrReimbursementClaimStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;

        return [
            'employee_id' => ['nullable', 'uuid', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'currency_id' => ['nullable', 'uuid', Rule::exists('currencies', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string'],
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'uuid'],
            'lines.*.category_id' => ['required', 'uuid', Rule::exists('hr_reimbursement_categories', 'id')->where('company_id', $companyId)],
            'lines.*.expense_date' => ['required', 'date'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.project_id' => ['nullable', 'uuid', Rule::exists('projects', 'id')->where('company_id', $companyId)],
        ];
    }
}
