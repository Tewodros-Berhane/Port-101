<?php

namespace App\Http\Requests\Integrations;

use App\Modules\Integrations\WebhookEventCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookEndpointUpdateRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
            'subscribed_events' => ['required', 'array', 'min:1'],
            'subscribed_events.*' => ['required', 'string', Rule::in($allowedEvents)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $url = trim((string) $this->input('target_url', ''));

            if ($url === '') {
                return;
            }

            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            if (app()->isProduction() && $scheme !== 'https') {
                $validator->errors()->add(
                    'target_url',
                    'Webhook endpoints must use HTTPS in production.',
                );
            }
        });
    }
}
