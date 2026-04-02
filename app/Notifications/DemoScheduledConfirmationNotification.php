<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemoScheduledConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ContactRequest $contactRequest,
        public ?string $previousScheduledDemoDate = null,
        public ?string $reason = null,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $scheduledDate = $this->contactRequest->scheduled_demo_date?->format('F j, Y');
        $preferredDate = $this->contactRequest->preferred_demo_date?->format('F j, Y');
        $scheduledDateChanged = $this->previousScheduledDemoDate !== $this->contactRequest->scheduled_demo_date?->toDateString();
        $firstConfirmation = $this->previousScheduledDemoDate === null;
        $subject = $firstConfirmation
            ? 'Your Port-101 demo has been scheduled'
            : 'Your Port-101 demo date has been updated';

        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$this->contactRequest->full_name},");

        if ($scheduledDate && $preferredDate && $firstConfirmation) {
            if ($this->contactRequest->scheduled_demo_date?->toDateString() === $this->contactRequest->preferred_demo_date?->toDateString()) {
                $mailMessage->line("You requested a Port-101 demo for {$preferredDate}, and we have scheduled your walkthrough for that date.");
            } else {
                $mailMessage
                    ->line("You requested a Port-101 demo for {$preferredDate}.")
                    ->line("We are not able to host the walkthrough on that date, and your confirmed demo date is {$scheduledDate}.");
            }
        } elseif ($scheduledDateChanged && $scheduledDate) {
            $mailMessage
                ->line("Your Port-101 demo was previously scheduled for {$this->formatDate($this->previousScheduledDemoDate)}.")
                ->line("The updated confirmed demo date is {$scheduledDate}.");

            if ($preferredDate) {
                $mailMessage->line("Your originally requested date was {$preferredDate}.");
            }
        }

        if ($this->reason) {
            $mailMessage->line("Reason for the change: {$this->reason}");
        }

        return $mailMessage
            ->line('If the confirmed date no longer works, reply to this email and the Port-101 team will coordinate the next available slot.')
            ->line("Company: {$this->contactRequest->company_name}")
            ->line("Role: {$this->contactRequest->role_title}")
            ->salutation('Port-101');
    }

    private function formatDate(?string $date): string
    {
        if (! $date) {
            return 'Not set';
        }

        return \Carbon\CarbonImmutable::parse($date)->format('F j, Y');
    }
}
