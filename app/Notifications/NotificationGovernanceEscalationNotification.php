<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotificationGovernanceEscalationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $eventName,
        public string $severity,
        public string $source,
        public int $escalationDelayMinutes,
        public string $details = ''
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
        $delayText = $this->escalationDelayMinutes > 0
            ? "Escalation window: {$this->escalationDelayMinutes} minutes."
            : '';

        return [
            'title' => 'Governance escalation triggered',
            'message' => trim("{$this->eventName} triggered a {$this->severity} severity escalation. {$delayText}"),
            'url' => '/platform/dashboard',
            'severity' => 'critical',
            'meta' => [
                'event' => $this->eventName,
                'severity' => $this->severity,
                'source' => $this->source,
                'details' => $this->details,
                'escalation_delay_minutes' => $this->escalationDelayMinutes,
            ],
        ];
    }
}

