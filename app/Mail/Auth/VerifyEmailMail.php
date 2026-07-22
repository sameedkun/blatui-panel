<?php

namespace App\Mail\Auth;

use App\Enum\MailPurpose;
use App\Mail\Concerns\HasMailPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for email verification using Markdown view and {@see MailPurpose::Auth}.
 */
class VerifyEmailMail extends Mailable
{
    use HasMailPurpose, Queueable, SerializesModels;

    protected MailPurpose $purpose = MailPurpose::Auth;

    public function __construct(public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Email Address',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.verify-email',
            with: [
                'url' => $this->url,
            ],
        );
    }
}
