<?php

namespace App\Mail\Concerns;

use App\Enum\MailPurpose;
use App\Services\MailConfigurator;

/**
 * Trait for Mailables to declare their intended {@see MailPurpose}.
 *
 * Automatically resolves and applies the appropriate From address & name
 * (via {@see MailConfigurator}) during the mailable building lifecycle.
 *
 * Usage:
 * <code>
 * class VerifyEmailMail extends Mailable
 * {
 *     use HasMailPurpose;
 *
 *     protected MailPurpose $purpose = MailPurpose::Auth;
 * }
 * </code>
 */
trait HasMailPurpose
{
    /**
     * Configure and apply the purpose-based From identity to this mailable.
     */
    public function configurePurposeSender(?MailPurpose $purpose = null): static
    {
        $targetPurpose = $purpose
            ?? ($this->purpose ?? null)
            ?? MailPurpose::Default;
        $sender = app(MailConfigurator::class)->getSender($targetPurpose);

        if (! empty($sender['address'])) {
            $this->from($sender['address'], $sender['name'] ?? config('app.name'));
        }

        return $this;
    }

    /**
     * Hook into Mailable's standard `buildFrom` lifecycle to apply purpose sender.
     */
    protected function buildFrom($message): static
    {
        if (empty($this->from)) {
            $this->configurePurposeSender();
        }

        return parent::buildFrom($message);
    }
}
