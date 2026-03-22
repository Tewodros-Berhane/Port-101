<?php

namespace App\Http\Requests\Projects;

use App\Modules\Projects\Models\ProjectMilestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectMilestoneStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sequence' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'status' => ['required', 'string', Rule::in($this->allowedStatuses())],
            'due_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatuses(): array
    {
        return array_values(array_filter(
            ProjectMilestone::STATUSES,
            fn (string $status) => $status !== ProjectMilestone::STATUS_BILLED,
        ));
    }
}
