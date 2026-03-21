<?php

namespace App\Http\Requests\Projects;

use App\Http\Requests\Core\Concerns\CompanyMemberExistsRule;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Projects\Models\ProjectTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectTaskUpdateRequest extends FormRequest
{
    use CompanyMemberExistsRule;
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taskId = (string) $this->route('task')?->id;

        return [
            'stage_id' => ['nullable', 'uuid', $this->companyScopedExists('project_stages')],
            'parent_task_id' => ['nullable', 'uuid', $this->companyScopedExists('project_tasks')],
            'customer_id' => ['nullable', 'uuid', $this->companyScopedExists('partners')],
            'task_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique('project_tasks', 'task_number')
                    ->where('company_id', $this->user()?->current_company_id)
                    ->ignore($taskId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(ProjectTask::STATUSES)],
            'priority' => ['required', 'string', Rule::in(ProjectTask::PRIORITIES)],
            'assigned_to' => ['nullable', 'uuid', $this->companyMemberExists()],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'is_billable' => ['required', 'boolean'],
            'billing_status' => ['required', 'string', Rule::in(ProjectTask::BILLING_STATUSES)],
        ];
    }
}
