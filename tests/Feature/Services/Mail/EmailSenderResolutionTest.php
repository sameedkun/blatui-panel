<?php

namespace Tests\Feature;

use App\Enum\MailPurpose;
use App\Models\EmailDomain;
use App\Models\EmailSender;
use App\Models\SmtpSetting;
use Database\Seeders\EmailSendersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailSenderResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_the_configured_senders_address_for_a_purpose_with_an_active_domain(): void
    {
        $domain = EmailDomain::factory()->create(['domain' => 'auth.example.com', 'is_active' => true]);
        EmailSender::factory()->create([
            'key' => MailPurpose::Auth,
            'email_domain_id' => $domain->id,
            'local_part' => 'login',
            'from_name' => 'Example Auth',
            'is_enabled' => true,
        ]);

        $result = EmailSender::resolve(MailPurpose::Auth);

        $this->assertSame('login@auth.example.com', $result['address']);
        $this->assertSame('Example Auth', $result['name']);
    }

    public function test_resolve_falls_back_to_the_default_purpose_when_the_requested_purpose_has_no_domain(): void
    {
        $domain = EmailDomain::factory()->create(['domain' => 'mail.example.com', 'is_active' => true]);
        EmailSender::factory()->create([
            'key' => MailPurpose::Default,
            'email_domain_id' => $domain->id,
            'local_part' => 'noreply',
            'from_name' => 'Example',
            'is_enabled' => true,
        ]);
        // Notifications sender exists but has no domain assigned.
        EmailSender::factory()->create([
            'key' => MailPurpose::Notifications,
            'email_domain_id' => null,
            'is_enabled' => true,
        ]);

        $result = EmailSender::resolve(MailPurpose::Notifications);

        $this->assertSame('noreply@mail.example.com', $result['address']);
        $this->assertSame('Example', $result['name']);
    }

    public function test_resolve_falls_back_to_the_default_purpose_when_the_requested_purpose_is_disabled(): void
    {
        $domain = EmailDomain::factory()->create(['domain' => 'mail.example.com', 'is_active' => true]);
        EmailSender::factory()->create([
            'key' => MailPurpose::Default,
            'email_domain_id' => $domain->id,
            'is_enabled' => true,
        ]);
        $disabledDomain = EmailDomain::factory()->create(['domain' => 'billing.example.com', 'is_active' => true]);
        EmailSender::factory()->create([
            'key' => MailPurpose::Billing,
            'email_domain_id' => $disabledDomain->id,
            'is_enabled' => false,
        ]);

        $result = EmailSender::resolve(MailPurpose::Billing);

        $this->assertSame('noreply@mail.example.com', $result['address']);
    }

    public function test_resolve_falls_back_to_the_default_purpose_when_the_assigned_domain_is_inactive(): void
    {
        $active = EmailDomain::factory()->create(['domain' => 'mail.example.com', 'is_active' => true]);
        EmailSender::factory()->create(['key' => MailPurpose::Default, 'email_domain_id' => $active->id]);

        $inactive = EmailDomain::factory()->create(['domain' => 'marketing.example.com', 'is_active' => false]);
        EmailSender::factory()->create(['key' => MailPurpose::Marketing, 'email_domain_id' => $inactive->id]);

        $result = EmailSender::resolve(MailPurpose::Marketing);

        $this->assertSame('noreply@mail.example.com', $result['address']);
    }

    public function test_resolve_falls_back_to_static_mail_config_when_nothing_is_configured_at_all(): void
    {
        config(['mail.from' => ['address' => 'hello@fallback.test', 'name' => 'Fallback']]);

        $result = EmailSender::resolve(MailPurpose::Support);

        $this->assertSame('hello@fallback.test', $result['address']);
        $this->assertSame('Fallback', $result['name']);
    }

    public function test_active_scope_excludes_inactive_domains(): void
    {
        EmailDomain::factory()->create(['is_active' => true]);
        EmailDomain::factory()->create(['is_active' => false]);

        $this->assertSame(1, EmailDomain::query()->active()->count());
    }

    public function test_smtp_password_round_trips_through_the_encrypted_cast(): void
    {
        SmtpSetting::query()->create([
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mailer',
            'password' => 'super-secret',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Example',
        ]);

        $fresh = SmtpSetting::current();

        $this->assertSame('super-secret', $fresh->password);
        $this->assertDatabaseMissing('smtp_settings', ['password' => 'super-secret']);
    }

    public function test_the_seeder_is_idempotent_and_never_resets_an_already_configured_sender(): void
    {
        $this->seed(EmailSendersSeeder::class);
        $this->assertSame(count(MailPurpose::cases()), EmailSender::query()->count());

        EmailSender::query()->where('key', MailPurpose::Auth)->update(['from_name' => 'Customised']);

        $this->seed(EmailSendersSeeder::class);

        $this->assertSame(count(MailPurpose::cases()), EmailSender::query()->count());
        $this->assertSame('Customised', EmailSender::query()->where('key', MailPurpose::Auth)->first()->from_name);
    }
}
