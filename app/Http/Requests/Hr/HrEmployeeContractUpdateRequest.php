<?php

namespace App\Http\Requests\Hr;

use App\Modules\Hr\Models\HrEmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrEmployeeContractUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;

        return [
            'contract_number' => ['required', 'string', 'max:64'],
            'status' => ['required', Rule::in(HrEmployeeContract::STATUSES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'pay_frequency' => ['required', Rule::in(HrEmployeeContract::PAY_FREQUENCIES)],
            'salary_basis' => ['required', Rule::in(HrEmployeeContract::SALARY_BASES)],
            'base_salary_amount' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'uuid', Rule::exists('currencies', 'id')->where('company_id', $companyId)],
            'working_days_per_week' => ['required', 'integer', 'between:1,7'],
            'standard_hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'is_payroll_eligible' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
