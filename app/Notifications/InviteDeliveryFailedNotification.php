<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InviteDeliveryFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $inviteEmail,
        public string $contextLabel,
        public string $errorMessage,
        public bool $isPlatformInvite = false
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
            'title' => 'Invite delivery failed',
            'message' => "Invite email to {$this->inviteEmail} failed for {$this->contextLabel}.",
            'url' => $this->isPlatformInvite ? '/platform/invites' : '/core/invites',
            'severity' => 'high',
            'meta' => [
                'invite_email' => $this->inviteEmail,
                'context' => $this->contextLabel,
                'error' => $this->errorMessage,
                'is_platform_invite' => $this->isPlatformInvite,
            ],
        ];
    }
}
