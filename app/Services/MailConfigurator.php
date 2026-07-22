<?php

namespace App\Services;

use App\Enum\MailPurpose;
use App\Models\EmailSender;
use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves email sender credentials and identity based on active mail transport:
 *  - 'smtp'   → Single cached SMTP configuration and single From identity.
 *  - 'resend' → Purpose-based sender resolution via {@see EmailSender::resolve()}.
 */
class MailConfigurator
{
    public const string SMTP_CACHE_KEY = 'settings.smtp_config';

    /**
     * Resolve the from-address and from-name for a specific purpose.
     *
     * @return array{address: ?string, name: ?string}
     */
    public function getSender(MailPurpose $purpose = MailPurpose::Default): array
    {
        $driver = config('mail.default');

        if ($driver === 'smtp') {
            return $this->getSmtpSender();
        }

        if ($driver === 'resend') {
            return EmailSender::resolve($purpose);
        }

        return [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ];
    }

    /**
     * Hydrate Laravel's runtime SMTP configuration from DB/cache if active driver is SMTP.
     */
    public function hydrateSmtpConfigIfNeeded(): void
    {
        if (config('mail.default') !== 'smtp') {
            return;
        }

        $settings = $this->getCachedSmtpSettings();

        if ($settings === null) {
            return;
        }

        config([
            'mail.mailers.smtp.host' => $settings['host'] ?? config('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => $settings['port'] ?? config('mail.mailers.smtp.port'),
            'mail.mailers.smtp.encryption' => $settings['encryption'] ?? config('mail.mailers.smtp.encryption'),
            'mail.mailers.smtp.username' => $settings['username'] ?? config('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => $settings['password'] ?? config('mail.mailers.smtp.password'),
            'mail.from.address' => $settings['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['from_name'] ?? config('mail.from.name'),
        ]);
    }

    /**
     * Clear cached SMTP settings (call when SmtpSetting is saved or updated).
     */
    public static function clearCache(): void
    {
        Cache::forget(self::SMTP_CACHE_KEY);
    }

    /**
     * @return array{address: ?string, name: ?string}
     */
    protected function getSmtpSender(): array
    {
        $this->hydrateSmtpConfigIfNeeded();

        $settings = $this->getCachedSmtpSettings();

        return [
            'address' => $settings['from_address'] ?? config('mail.from.address'),
            'name' => $settings['from_name'] ?? config('mail.from.name'),
        ];
    }

    /**
     * Fetch cached SMTP settings array, or remember from DB.
     *
     * @return array{host: ?string, port: ?int, encryption: ?string, username: ?string, password: ?string, from_address: ?string, from_name: ?string}|null
     */
    protected function getCachedSmtpSettings(): ?array
    {
        return Cache::remember(self::SMTP_CACHE_KEY, now()->addDay(), function (): ?array {
            $smtp = SmtpSetting::query()->first();

            if (! $smtp) {
                return null;
            }

            return [
                'host' => $smtp->host,
                'port' => $smtp->port,
                'encryption' => $smtp->encryption,
                'username' => $smtp->username,
                'password' => $smtp->password,
                'from_address' => $smtp->from_address,
                'from_name' => $smtp->from_name,
            ];
        });
    }
}
