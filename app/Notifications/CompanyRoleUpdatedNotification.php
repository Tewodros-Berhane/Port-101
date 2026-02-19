<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyRoleUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public string $roleName,
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
            'title' => 'Role updated',
            'message' => "{$this->updatedBy} changed your role to {$this->roleName} in {$this->companyName}.",
            'url' => '/company/users',
            'severity' => 'medium',
            'meta' => [
                'company' => $this->companyName,
                'role' => $this->roleName,
                'updated_by' => $this->updatedBy,
            ],
        ];
    }
}
