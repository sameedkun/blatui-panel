<?php

namespace App\Mail\Account;

use App\Enum\MailPurpose;
use App\Mail\Concerns\HasMailPurpose;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable informing a user their account has been scheduled for deletion,
 * using {@see MailPurpose::Notifications}. Wording differs depending on
 * whether the request was self-initiated (cancellable) or admin-initiated
 * (not cancellable by the user).
 */
class AccountDeletionScheduledMail extends Mailable
{
    use HasMailPurpose, Queueable, SerializesModels;

    protected MailPurpose $purpose = MailPurpose::Notifications;

    public function __construct(public CarbonInterface $purgesAt, public bool $initiatedByAdmin) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Account Is Scheduled for Deletion',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.account.deletion-scheduled',
            with: [
                'purgesAt' => $this->purgesAt,
                'initiatedByAdmin' => $this->initiatedByAdmin,
            ],
        );
    }
}
