<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrLeaveAllocationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;

        return [
            'employee_id' => ['required', 'uuid', Rule::exists('hr_employees', 'id')->where('company_id', $companyId)],
            'leave_type_id' => ['required', 'uuid', Rule::exists('hr_leave_types', 'id')->where('company_id', $companyId)],
            'leave_period_id' => ['required', 'uuid', Rule::exists('hr_leave_periods', 'id')->where('company_id', $companyId)],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
            'used_amount' => ['nullable', 'numeric', 'min:0'],
            'carry_forward_amount' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
