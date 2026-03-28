<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Core\Concerns\CompanyMemberExistsRule;
use App\Modules\Hr\Models\HrEmployee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HrEmployeeUpdateRequest extends FormRequest
{
    use CompanyMemberExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (string) $this->user()?->current_company_id;
        $employeeId = (string) $this->route('employee')?->id;

        return [
            'user_id' => [
                'nullable',
                'uuid',
                $this->companyMemberExists(),
                Rule::unique('hr_employees', 'user_id')
                    ->where('company_id', $companyId)
                    ->ignore($employeeId),
            ],
            'requires_system_access' => ['nullable', 'boolean'],
            'department_id' => ['nullable', 'uuid', Rule::exists('hr_departments', 'id')->where('company_id', $companyId)],
            'department_name' => ['nullable', 'string', 'max:120'],
            'designation_id' => ['nullable', 'uuid', Rule::exists('hr_designations', 'id')->where('company_id', $companyId)],
            'designation_name' => ['nullable', 'string', 'max:120'],
            'system_role_id' => [
                'nullable',
                'uuid',
                Rule::exists('roles', 'id')->where(function ($query) use ($companyId): void {
                    $query
                        ->where(function ($nested) use ($companyId): void {
                            $nested->whereNull('company_id')
                                ->orWhere('company_id', $companyId);
                        })
                        ->where('slug', '!=', 'owner');
                }),
            ],
            'employee_number' => ['nullable', 'string', 'max:64'],
            'employment_status' => ['required', Rule::in(HrEmployee::STATUSES)],
            'employment_type' => ['required', Rule::in(HrEmployee::TYPES)],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'login_email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('hr_employees', 'login_email')
                    ->where('company_id', $companyId)
                    ->ignore($employeeId),
            ],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'work_phone' => ['nullable', 'string', 'max:40'],
            'personal_phone' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'manager_employee_id' => ['nullable', 'uuid', Rule::exists('hr_employees', 'id')->where('company_id', $companyId), Rule::notIn([$employeeId])],
            'attendance_approver_user_id' => ['nullable', 'uuid', $this->companyMemberExists()],
            'leave_approver_user_id' => ['nullable', 'uuid', $this->companyMemberExists()],
            'reimbursement_approver_user_id' => ['nullable', 'uuid', $this->companyMemberExists()],
            'timezone' => ['nullable', 'string', 'max:64'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'bank_account_reference' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $requiresSystemAccess = filter_var($this->input('requires_system_access', false), FILTER_VALIDATE_BOOL);
            $userId = trim((string) $this->input('user_id', ''));
            $loginEmail = trim((string) $this->input('login_email', ''));
            $systemRoleId = trim((string) $this->input('system_role_id', ''));

            if (! $requiresSystemAccess && ($userId !== '' || $loginEmail !== '' || $systemRoleId !== '')) {
                $validator->errors()->add('requires_system_access', 'Enable system access before assigning a role, login email, or linked user.');
            }

            if (! $requiresSystemAccess) {
                return;
            }

            if (! $this->user()?->hasPermission('hr.employee_access.manage')) {
                $validator->errors()->add('requires_system_access', 'You do not have permission to grant system access.');
            }

            if ($systemRoleId === '') {
                $validator->errors()->add('system_role_id', 'Select a system role for this employee.');
            }

            if ($userId === '' && $loginEmail === '') {
                $validator->errors()->add('login_email', 'Provide a login email or link an existing company user.');
            }
        });
    }
}
