<?php

namespace App\Http\Requests\Hr;

use App\Modules\Hr\Models\HrLeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrLeaveTypeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;
        $leaveTypeId = (string) $this->route('leaveType')?->id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('hr_leave_types', 'name')->where('company_id', $companyId)->ignore($leaveTypeId)],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('hr_leave_types', 'code')->where('company_id', $companyId)->ignore($leaveTypeId)],
            'unit' => ['required', Rule::in(HrLeaveType::UNITS)],
            'requires_allocation' => ['required', 'boolean'],
            'is_paid' => ['required', 'boolean'],
            'requires_approval' => ['required', 'boolean'],
            'allow_negative_balance' => ['required', 'boolean'],
            'max_consecutive_days' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:16'],
        ];
    }
}
