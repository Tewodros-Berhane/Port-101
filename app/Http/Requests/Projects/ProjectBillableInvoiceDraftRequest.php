<?php

namespace App\Http\Requests\Projects;

use App\Modules\Projects\ProjectInvoiceDraftService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectBillableInvoiceDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billable_ids' => ['required', 'array', 'min:1'],
            'billable_ids.*' => ['required', 'uuid'],
            'group_by' => [
                'nullable',
                Rule::in(ProjectInvoiceDraftService::GROUP_BY_OPTIONS),
            ],
        ];
    }
}
