<?php

namespace App\Http\Requests\Hr;

use App\Modules\Hr\Models\HrAttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrAttendanceRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;

        return [
            'employee_id' => [
                'nullable',
                'uuid',
                Rule::exists('hr_employees', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'requested_status' => ['required', 'string', Rule::in(HrAttendanceRecord::STATUSES)],
            'requested_check_in_at' => ['nullable', 'string', 'max:32'],
            'requested_check_out_at' => ['nullable', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:2000'],
            'action' => ['nullable', 'string', Rule::in(['save', 'submit'])],
        ];
    }
}
