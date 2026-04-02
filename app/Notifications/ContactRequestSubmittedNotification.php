<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContactRequestSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ContactRequest $contactRequest
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $requestTypeLabel = $this->contactRequest->request_type === ContactRequest::REQUEST_TYPE_DEMO
            ? 'demo request'
            : 'sales request';

        return [
            'title' => $this->contactRequest->request_type === ContactRequest::REQUEST_TYPE_DEMO
                ? 'New demo request'
                : 'New sales inquiry',
            'message' => "{$this->contactRequest->full_name} from {$this->contactRequest->company_name} submitted a {$requestTypeLabel}.",
            'url' => '/platform/contact-requests',
            'severity' => 'medium',
            'meta' => [
                'contact_request_id' => $this->contactRequest->id,
                'request_type' => $this->contactRequest->request_type,
                'work_email' => $this->contactRequest->work_email,
                'company_name' => $this->contactRequest->company_name,
                'status' => $this->contactRequest->status,
            ],
        ];
    }
}
