<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlatformOperationsAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $severity,
        public string $status = 'open',
        public string $url = '/platform/operations/queue-health',
        public array $meta = [],
    ) {}

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
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'severity' => $this->severity,
            'meta' => [
                'status' => $this->status,
                ...$this->meta,
            ],
        ];
    }
}
