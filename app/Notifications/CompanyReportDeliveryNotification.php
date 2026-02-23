<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompanyReportDeliveryNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, string|int|float>  $summary
     */
    public function __construct(
        public string $companyName,
        public string $reportTitle,
        public string $presetName,
        public string $periodLabel,
        public string $format,
        public string $link,
        public array $summary,
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
        $formatLabel = strtoupper($this->format);
        $rowCount = (int) ($this->summary['rows'] ?? 0);

        return [
            'title' => 'Scheduled company report delivered',
            'message' => "{$this->companyName}: {$this->reportTitle} ({$this->presetName}, {$this->periodLabel}) is ready in {$formatLabel} with {$rowCount} rows.",
            'url' => '/company/reports',
            'severity' => 'low',
            'meta' => [
                'company' => $this->companyName,
                'report_title' => $this->reportTitle,
                'preset_name' => $this->presetName,
                'period' => $this->periodLabel,
                'format' => $this->format,
                'summary' => $this->summary,
                'link' => $this->link,
            ],
        ];
    }
}
