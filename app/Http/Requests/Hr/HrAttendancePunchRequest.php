<?php

namespace App\Http\Requests\Hr;

use App\Modules\Hr\Models\HrAttendanceCheckin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrAttendancePunchRequest extends FormRequest
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
            'recorded_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', Rule::in(HrAttendanceCheckin::SOURCES)],
            'device_reference' => ['nullable', 'string', 'max:128'],
            'location_data' => ['nullable', 'array'],
        ];
    }
}
