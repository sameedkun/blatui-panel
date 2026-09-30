<?php

namespace App\Mail\Billing;

use App\Enum\MailPurpose;
use App\Enum\PaymentProvider;
use App\Mail\Concerns\HasMailPurpose;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable telling a subscriber a renewal charge failed, using
 * {@see MailPurpose::Billing}. With `$graceEndsAt` access continues until
 * then while the store retries; without it, access is paused until the
 * payment method is fixed.
 */
class PaymentFailedMail extends Mailable
{
    use HasMailPurpose, Queueable, SerializesModels;

    protected MailPurpose $purpose = MailPurpose::Billing;

    public function __construct(
        public string $planName,
        public PaymentProvider $provider,
        public ?CarbonInterface $graceEndsAt = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->graceEndsAt
                ? "Action Needed: Payment for {$this->planName} Failed"
                : "Your {$this->planName} Subscription Is Paused",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.billing.payment-failed',
            with: [
                'planName' => $this->planName,
                'storeName' => $this->provider->label(),
                'manageUrl' => $this->provider->manageSubscriptionUrl(),
                'graceEndsAt' => $this->graceEndsAt,
            ],
        );
    }
}
