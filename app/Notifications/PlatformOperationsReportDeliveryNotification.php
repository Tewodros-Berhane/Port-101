<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlatformOperationsReportDeliveryNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, string>  $links
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public string $presetName,
        public string $periodLabel,
        public string $format,
        public array $links,
        public array $summary
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
        $adminActions = (int) ($this->summary['admin_actions'] ?? 0);
        $invites = (int) ($this->summary['delivery_total'] ?? 0);
        $formatLabel = strtoupper($this->format);

        return [
            'title' => 'Scheduled operations report delivered',
            'message' => "Preset \"{$this->presetName}\" ({$this->periodLabel}) is ready in {$formatLabel}. Admin actions: {$adminActions}, invite deliveries: {$invites}.",
            'url' => '/platform/reports',
            'severity' => 'low',
            'meta' => [
                'preset_name' => $this->presetName,
                'period' => $this->periodLabel,
                'format' => $this->format,
                'summary' => $this->summary,
                'links' => $this->links,
            ],
        ];
    }
}
