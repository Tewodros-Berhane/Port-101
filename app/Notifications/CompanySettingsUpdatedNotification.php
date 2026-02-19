<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanySettingsUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public string $updatedBy
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
        return [
            'title' => 'Company settings updated',
            'message' => "{$this->updatedBy} updated company settings for {$this->companyName}.",
            'url' => '/company/settings',
            'severity' => 'medium',
            'meta' => [
                'company' => $this->companyName,
                'updated_by' => $this->updatedBy,
            ],
        ];
    }
}
