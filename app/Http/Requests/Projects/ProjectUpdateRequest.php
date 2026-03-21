<?php

namespace App\Http\Requests\Projects;

use App\Http\Requests\Core\Concerns\CompanyMemberExistsRule;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectUpdateRequest extends FormRequest
{
    use CompanyMemberExistsRule;
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = (string) $this->route('project')?->id;

        return [
            'customer_id' => ['nullable', 'uuid', $this->companyScopedExists('partners')],
            'sales_order_id' => ['nullable', 'uuid', $this->companyScopedExists('sales_orders')],
            'currency_id' => ['required', 'uuid', $this->companyScopedExists('currencies')],
            'project_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('projects', 'project_code')
                    ->where('company_id', $this->user()?->current_company_id)
                    ->ignore($projectId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(Project::STATUSES)],
            'billing_type' => ['required', 'string', Rule::in(Project::BILLING_TYPES)],
            'project_manager_id' => ['required', 'uuid', $this->companyMemberExists()],
            'start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'budget_hours' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'progress_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'health_status' => ['required', 'string', Rule::in(Project::HEALTH_STATUSES)],
        ];
    }
}
