<?php

namespace App\Models;

use App\Enum\MailPurpose;
use Database\Factories\EmailSenderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per {@see MailPurpose} case (seeded by EmailSendersSeeder, never
 * freely created/deleted from the admin side) — assigns a purpose to a
 * domain + local-part + from-name for Resend sending.
 */
#[Fillable([
    'key',
    'label',
    'email_domain_id',
    'local_part',
    'from_name',
    'is_enabled',
])]
class EmailSender extends Model
{
    /** @use HasFactory<EmailSenderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'key' => MailPurpose::class,
            'is_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<EmailDomain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(EmailDomain::class, 'email_domain_id');
    }

    /** The full from-address, only when an active domain is assigned. */
    protected function fromAddress(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->domain && $this->domain->is_active
                ? "{$this->local_part}@{$this->domain->domain}"
                : null,
        );
    }

    /**
     * Resolve the from-address/name to use for a given purpose: the purpose's
     * own enabled sender (if it has a usable domain) → the Default purpose's
     * sender → the app's static `mail.from` config.
     *
     * @return array{address: ?string, name: ?string}
     */
    public static function resolve(MailPurpose $purpose): array
    {
        $sender = static::query()
            ->where('key', $purpose->value)
            ->where('is_enabled', true)
            ->with('domain')
            ->first();

        if ($sender?->fromAddress !== null) {
            return ['address' => $sender->fromAddress, 'name' => $sender->from_name];
        }

        if ($purpose !== MailPurpose::Default) {
            return static::resolve(MailPurpose::Default);
        }

        return [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ];
    }
}
