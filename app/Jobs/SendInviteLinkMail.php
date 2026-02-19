<?php

namespace App\Jobs;

use App\Core\Access\Models\Invite;
use App\Mail\InviteLinkMail;
use App\Notifications\InviteDeliveryFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInviteLinkMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $inviteId)
    {
    }

    public function handle(): void
    {
        $invite = Invite::query()->find($this->inviteId);

        if (! $invite || $invite->accepted_at) {
            return;
        }

        $invite->increment('delivery_attempts');

        $invite->forceFill([
            'delivery_status' => Invite::DELIVERY_PENDING,
            'last_delivery_error' => null,
        ])->save();

        try {
            Mail::to($invite->email)->send(new InviteLinkMail(
                $invite,
                rtrim(config('app.url'), '/').'/invites/'.$invite->token
            ));
        } catch (Throwable $exception) {
            $invite->forceFill([
                'delivery_status' => Invite::DELIVERY_FAILED,
                'last_delivery_error' => $this->truncatedError($exception),
            ])->save();

            throw $exception;
        }

        $invite->forceFill([
            'delivery_status' => Invite::DELIVERY_SENT,
            'last_delivery_at' => now(),
            'last_delivery_error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $invite = Invite::query()->find($this->inviteId);

        if (! $invite) {
            return;
        }

        $invite->forceFill([
            'delivery_status' => Invite::DELIVERY_FAILED,
            'last_delivery_error' => $this->truncatedError($exception),
        ])->save();

        $invite->loadMissing('company:id,name', 'creator:id,name,email');

        $creator = $invite->creator;
        if (! $creator) {
            return;
        }

        $contextLabel = $invite->company?->name ?? 'platform';

        $creator->notify(new InviteDeliveryFailedNotification(
            inviteEmail: $invite->email,
            contextLabel: $contextLabel,
            errorMessage: $invite->last_delivery_error ?? 'Invite delivery failed.',
            isPlatformInvite: ! $invite->company_id
        ));
    }

    private function truncatedError(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (strlen($message) <= 500) {
            return $message;
        }

        return substr($message, 0, 497).'...';
    }
}
