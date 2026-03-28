<?php

namespace App\Http\Requests\Hr;

use App\Modules\Hr\Models\HrSalaryStructureLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrSalaryStructureStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
            'lines' => ['array'],
            'lines.*.line_type' => ['required', 'string', Rule::in(HrSalaryStructureLine::TYPES)],
            'lines.*.calculation_type' => ['required', 'string', Rule::in(HrSalaryStructureLine::CALCULATION_TYPES)],
            'lines.*.code' => ['required', 'string', 'max:64'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.percentage_rate' => ['nullable', 'numeric', 'min:0'],
            'lines.*.is_active' => ['required', 'boolean'],
        ];
    }
}
