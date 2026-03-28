<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Core\Concerns\CompanyMemberExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrPayrollRunStoreRequest extends FormRequest
{
    use CompanyMemberExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->current_company_id;

        return [
            'payroll_period_id' => ['required', Rule::exists('hr_payroll_periods', 'id')->where('company_id', $companyId)],
            'approver_user_id' => ['nullable', $this->companyMemberExists('user_id')],
        ];
    }
}
