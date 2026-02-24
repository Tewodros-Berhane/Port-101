<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformOperationsReportDeliveryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, string>  $links
     * @param  array<int, array{filename: string, mime: string, content: string}>  $attachmentsData
     */
    public function __construct(
        public string $presetName,
        public string $periodLabel,
        public string $format,
        public array $summary,
        public array $links,
        public array $attachmentsData = []
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Port-101 Scheduled Operations Reports',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.platform.operations-report-delivery',
            with: [
                'presetName' => $this->presetName,
                'periodLabel' => $this->periodLabel,
                'format' => strtoupper($this->format),
                'summary' => $this->summary,
                'links' => $this->links,
            ]
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return collect($this->attachmentsData)
            ->filter(function ($item) {
                return is_array($item)
                    && isset($item['filename'], $item['mime'], $item['content']);
            })
            ->map(function (array $item) {
                return Attachment::fromData(
                    fn () => (string) $item['content'],
                    (string) $item['filename']
                )->withMime((string) $item['mime']);
            })
            ->values()
            ->all();
    }
}
