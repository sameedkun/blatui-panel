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
 * Mailable for password reset using Markdown view and {@see MailPurpose::Auth}.
 */
class ResetPasswordMail extends Mailable
{
    use HasMailPurpose, Queueable, SerializesModels;

    protected MailPurpose $purpose = MailPurpose::Auth;

    public function __construct(
        public string $url,
        public int $count = 60
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Password Notification',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.reset-password',
            with: [
                'url' => $this->url,
                'count' => $this->count,
            ],
        );
    }
}
