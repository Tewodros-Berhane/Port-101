<?php

namespace App\Mail;

use App\Core\Access\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InviteLinkMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Invite $invite,
        public string $inviteUrl
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Port-101 Invite'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invites.link',
            with: [
                'invite' => $this->invite,
                'inviteUrl' => $this->inviteUrl,
            ]
        );
    }
}
