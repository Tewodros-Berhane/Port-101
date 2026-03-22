<?php

namespace App\Http\Requests\Projects;

use App\Http\Requests\Core\Concerns\CompanyScopedExistsRule;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRecurringBillingStoreRequest extends FormRequest
{
    use CompanyScopedExistsRule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'uuid', $this->companyScopedExists('projects')],
            'customer_id' => ['required', 'uuid', $this->companyScopedExists('partners')],
            'currency_id' => ['required', 'uuid', $this->companyScopedExists('currencies')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'frequency' => ['required', 'string', Rule::in(ProjectRecurringBilling::FREQUENCIES)],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.9999'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'invoice_due_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'starts_on' => ['required', 'date'],
            'next_run_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:next_run_on'],
            'auto_create_invoice_draft' => ['nullable', 'boolean'],
            'invoice_grouping' => ['required', 'string', Rule::in(['project', 'customer'])],
            'status' => ['required', 'string', Rule::in([
                ProjectRecurringBilling::STATUS_DRAFT,
                ProjectRecurringBilling::STATUS_ACTIVE,
            ])],
        ];
    }
}
