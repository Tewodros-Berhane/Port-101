<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlatformNotificationDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, int>  $severityCounts
     */
    public function __construct(
        public string $periodLabel,
        public int $totalNotifications,
        public array $severityCounts
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
            'title' => 'Platform notification digest',
            'message' => "Summary for {$this->periodLabel}: {$this->totalNotifications} notifications.",
            'url' => '/core/notifications',
            'severity' => 'low',
            'meta' => [
                'period' => $this->periodLabel,
                'total' => $this->totalNotifications,
                'severity_counts' => $this->severityCounts,
            ],
        ];
    }
}

