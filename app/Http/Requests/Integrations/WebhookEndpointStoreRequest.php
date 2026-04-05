<?php

namespace App\Http\Requests\Integrations;

use App\Modules\Integrations\WebhookEventCatalog;
use App\Modules\Integrations\WebhookTargetSecurityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookEndpointStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedEvents = collect(WebhookEventCatalog::EVENTS)
            ->reject(fn (string $event) => $event === WebhookEventCatalog::SYSTEM_WEBHOOK_TEST)
            ->push('*')
            ->values()
            ->all();

        return [
            'name' => ['required', 'string', 'max:120'],
            'target_url' => ['required', 'string', 'max:2048', 'url'],
            'is_active' => ['sometimes', 'boolean'],
            'subscribed_events' => ['required', 'array', 'min:1'],
            'subscribed_events.*' => ['required', 'string', Rule::in($allowedEvents)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $error = app(WebhookTargetSecurityService::class)
                ->validationError($this->input('target_url'), 'Webhook target URL');

            if ($error !== null) {
                $validator->errors()->add('target_url', $error);
            }
        });
    }
}
