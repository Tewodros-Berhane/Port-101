<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrShiftAssignmentStoreRequest extends FormRequest
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
                'required',
                'uuid',
                Rule::exists('hr_employees', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'shift_id' => [
                'required',
                'uuid',
                Rule::exists('hr_shifts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'from_date' => ['required', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }
}
