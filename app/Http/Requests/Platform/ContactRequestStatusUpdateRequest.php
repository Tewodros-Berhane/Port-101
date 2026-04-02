<?php

namespace App\Http\Requests\Platform;

use App\Models\ContactRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequestStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contactRequest = $this->route('contactRequest');
        $statusOptions = ContactRequest::STATUS_OPTIONS;
        $isDemoRequest = $contactRequest?->request_type === ContactRequest::REQUEST_TYPE_DEMO;
        $currentPreferredDemoDate = $contactRequest?->preferred_demo_date?->toDateString();
        $currentScheduledDemoDate = $contactRequest?->scheduled_demo_date?->toDateString();
        $scheduledDemoDate = $this->input('scheduled_demo_date');
        $scheduledDemoDateChanged = $isDemoRequest
            && $scheduledDemoDate !== null
            && $scheduledDemoDate !== $currentScheduledDemoDate;
        $reasonRequired = $scheduledDemoDateChanged && (
            $currentScheduledDemoDate !== null
            || $scheduledDemoDate !== $currentPreferredDemoDate
        );

        if (! $isDemoRequest) {
            $statusOptions = array_values(array_filter(
                $statusOptions,
                fn (string $status) => $status !== ContactRequest::STATUS_DEMO_SCHEDULED,
            ));
        }

        return [
            'status' => ['required', 'string', Rule::in($statusOptions)],
            'scheduled_demo_date' => [
                Rule::prohibitedIf(! $isDemoRequest),
                Rule::requiredIf(
                    $isDemoRequest
                    && $this->input('status') === ContactRequest::STATUS_DEMO_SCHEDULED
                ),
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'preferred_demo_date' => ['prohibited'],
            'demo_date_change_reason' => [
                Rule::prohibitedIf(! $isDemoRequest),
                Rule::requiredIf($reasonRequired),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'demo_date_change_reason.required' => 'Add a reason when the confirmed demo date differs from the requested or previously scheduled date.',
            'preferred_demo_date.prohibited' => 'The requested demo date cannot be changed from the admin update flow.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => trim((string) $this->input('status')),
            'scheduled_demo_date' => $this->normalizeNullableString($this->input('scheduled_demo_date')),
            'demo_date_change_reason' => $this->normalizeNullableString($this->input('demo_date_change_reason')),
        ]);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
