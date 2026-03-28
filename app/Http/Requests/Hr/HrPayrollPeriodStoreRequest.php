<?php

namespace App\Http\Requests\Hr;

use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrPayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrPayrollPeriodStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'pay_frequency' => ['required', 'string', Rule::in(HrEmployeeContract::PAY_FREQUENCIES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'payment_date' => ['required', 'date', 'after_or_equal:end_date'],
            'status' => ['required', 'string', Rule::in(HrPayrollPeriod::STATUSES)],
        ];
    }
}
