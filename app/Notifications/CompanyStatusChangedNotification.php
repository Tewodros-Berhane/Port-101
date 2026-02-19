<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public bool $isActive,
        public string $changedBy
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
        $statusText = $this->isActive ? 'activated' : 'suspended';

        return [
            'title' => 'Company status changed',
            'message' => "{$this->changedBy} {$statusText} {$this->companyName}.",
            'url' => '/company/dashboard',
            'meta' => [
                'company' => $this->companyName,
                'is_active' => $this->isActive,
                'changed_by' => $this->changedBy,
            ],
        ];
    }
}

