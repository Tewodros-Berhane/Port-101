<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrLeaveRequestUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;

        return [
            'leave_type_id' => ['required', 'uuid', Rule::exists('hr_leave_types', 'id')->where('company_id', $companyId)],
            'leave_period_id' => ['nullable', 'uuid', Rule::exists('hr_leave_periods', 'id')->where('company_id', $companyId)],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'duration_amount' => ['nullable', 'numeric', 'min:0.25'],
            'is_half_day' => ['required', 'boolean'],
            'reason' => ['nullable', 'string'],
            'action' => ['required', Rule::in(['save', 'submit'])],
        ];
    }
}
