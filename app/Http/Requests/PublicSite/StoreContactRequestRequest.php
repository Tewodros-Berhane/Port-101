<?php

namespace App\Http\Requests\PublicSite;

use App\Models\ContactRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreContactRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_type' => ['required', 'string', Rule::in(ContactRequest::REQUEST_TYPES)],
            'full_name' => ['required', 'string', 'max:160'],
            'work_email' => ['required', 'email', 'max:255'],
            'company_name' => ['required', 'string', 'max:160'],
            'role_title' => ['required', 'string', 'max:160'],
            'team_size' => [
                'required',
                'string',
                Rule::in(array_column(ContactRequest::TEAM_SIZE_OPTIONS, 'value')),
            ],
            'preferred_demo_date' => [
                Rule::requiredIf($this->input('request_type') === ContactRequest::REQUEST_TYPE_DEMO),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'modules_interest' => ['nullable', 'array', 'max:8'],
            'modules_interest.*' => [
                'string',
                Rule::in(array_column(ContactRequest::MODULE_OPTIONS, 'value')),
            ],
            'message' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:120'],
            'source_page' => ['nullable', 'string', 'max:255'],
            'website' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) !== '') {
                        $fail('Invalid submission.');
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $trimmedModules = collect(Arr::wrap($this->input('modules_interest')))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'request_type' => strtolower(trim((string) $this->input('request_type'))),
            'full_name' => trim((string) $this->input('full_name')),
            'work_email' => strtolower(trim((string) $this->input('work_email'))),
            'company_name' => trim((string) $this->input('company_name')),
            'role_title' => trim((string) $this->input('role_title')),
            'team_size' => trim((string) $this->input('team_size')),
            'preferred_demo_date' => $this->normalizeNullableString($this->input('preferred_demo_date')),
            'modules_interest' => $trimmedModules,
            'message' => $this->normalizeNullableString($this->input('message')),
            'phone' => $this->normalizeNullableString($this->input('phone')),
            'country' => $this->normalizeNullableString($this->input('country')),
            'source_page' => $this->normalizeNullableString($this->input('source_page')),
            'website' => $this->normalizeNullableString($this->input('website')),
        ]);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
