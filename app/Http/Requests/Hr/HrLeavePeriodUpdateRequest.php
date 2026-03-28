<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrLeavePeriodUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;
        $leavePeriodId = (string) $this->route('leavePeriod')?->id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('hr_leave_periods', 'name')->where('company_id', $companyId)->ignore($leavePeriodId)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_closed' => ['required', 'boolean'],
        ];
    }
}
