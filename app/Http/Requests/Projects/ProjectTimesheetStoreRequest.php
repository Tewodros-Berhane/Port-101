<?php

namespace App\Http\Requests\Projects;

use App\Http\Requests\Core\Concerns\CompanyMemberExistsRule;
use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Projects\Models\ProjectTask;
use Illuminate\Foundation\Http\FormRequest;

class ProjectTimesheetStoreRequest extends FormRequest
{
    use CompanyMemberExistsRule;
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid', $this->companyMemberExists()],
            'task_id' => ['nullable', 'uuid', $this->companyScopedExists('project_tasks')],
            'work_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'is_billable' => ['required', 'boolean'],
            'cost_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'bill_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $projectId = (string) $this->route('project')?->id;
            $taskId = (string) $this->input('task_id', '');

            if ($projectId === '' || $taskId === '') {
                return;
            }

            $belongsToProject = ProjectTask::query()
                ->where('project_id', $projectId)
                ->where('id', $taskId)
                ->exists();

            if (! $belongsToProject) {
                $validator->errors()->add('task_id', 'Selected task must belong to the project.');
            }
        });
    }
}
