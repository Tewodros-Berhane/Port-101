<?php

namespace App\Http\Requests\Hr;

use App\Modules\Hr\Models\HrEmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrCompensationAssignmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;

        return [
            'employee_id' => ['required', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'contract_id' => ['nullable', Rule::exists('hr_employee_contracts', 'id')->where('company_id', $companyId)],
            'salary_structure_id' => ['nullable', Rule::exists('hr_salary_structures', 'id')->where('company_id', $companyId)],
            'currency_id' => ['nullable', Rule::exists('currencies', 'id')->where('company_id', $companyId)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'pay_frequency' => ['nullable', 'string', Rule::in(HrEmployeeContract::PAY_FREQUENCIES)],
            'salary_basis' => ['nullable', 'string', Rule::in(HrEmployeeContract::SALARY_BASES)],
            'base_salary_amount' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'payroll_group' => ['nullable', 'string', 'max:64'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
