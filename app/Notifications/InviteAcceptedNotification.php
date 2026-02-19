<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InviteAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $inviteeEmail,
        public string $companyName,
        public string $acceptedBy
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
            'title' => 'Invite accepted',
            'message' => "{$this->acceptedBy} ({$this->inviteeEmail}) accepted an invite for {$this->companyName}.",
            'url' => '/core/invites',
            'meta' => [
                'company' => $this->companyName,
                'invitee_email' => $this->inviteeEmail,
                'accepted_by' => $this->acceptedBy,
            ],
        ];
    }
}

