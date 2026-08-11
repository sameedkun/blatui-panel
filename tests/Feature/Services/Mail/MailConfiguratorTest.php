<?php

namespace Tests\Feature\Services\Mail;

use App\Enum\MailPurpose;
use App\Mail\Auth\ResetPasswordMail;
use App\Mail\Auth\VerifyEmailMail;
use App\Mail\Concerns\HasMailPurpose;
use App\Models\EmailDomain;
use App\Models\EmailSender;
use App\Models\SmtpSetting;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Services\Mail\Configurator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TestDummyMailable extends Mailable
{
    use HasMailPurpose;

    protected MailPurpose $purpose = MailPurpose::Auth;

    public function build(): self
    {
        return $this->html('Hello World');
    }
}

class MailConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Configurator::clearCache();
    }

    public function test_get_sender_returns_smtp_from_identity_when_driver_is_smtp(): void
    {
        config(['mail.default' => 'smtp']);

        SmtpSetting::query()->create([
            'host' => 'smtp.mailtrap.io',
            'port' => 2525,
            'encryption' => 'tls',
            'username' => 'user_123',
            'password' => 'pass_123',
            'from_address' => 'smtp-noreply@example.com',
            'from_name' => 'SMTP System',
        ]);

        $configurator = new Configurator;

        $authSender = $configurator->getSender(MailPurpose::Auth);
        $notifSender = $configurator->getSender(MailPurpose::Notifications);

        $this->assertSame('smtp-noreply@example.com', $authSender['address']);
        $this->assertSame('SMTP System', $authSender['name']);

        $this->assertSame('smtp-noreply@example.com', $notifSender['address']);
        $this->assertSame('SMTP System', $notifSender['name']);

        $this->assertSame('smtp.mailtrap.io', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
    }

    public function test_get_sender_resolves_purpose_based_sender_when_driver_is_resend(): void
    {
        config(['mail.default' => 'resend']);

        $domain = EmailDomain::factory()->create(['domain' => 'app.com', 'is_active' => true]);

        EmailSender::factory()->create([
            'key' => MailPurpose::Auth,
            'email_domain_id' => $domain->id,
            'local_part' => 'auth',
            'from_name' => 'Auth Team',
            'is_enabled' => true,
        ]);

        EmailSender::factory()->create([
            'key' => MailPurpose::Default,
            'email_domain_id' => $domain->id,
            'local_part' => 'noreply',
            'from_name' => 'Default App',
            'is_enabled' => true,
        ]);

        $configurator = new Configurator;

        $authSender = $configurator->getSender(MailPurpose::Auth);
        $billingSender = $configurator->getSender(MailPurpose::Billing);

        $this->assertSame('auth@app.com', $authSender['address']);
        $this->assertSame('Auth Team', $authSender['name']);

        // Unconfigured Billing purpose falls back to Default
        $this->assertSame('noreply@app.com', $billingSender['address']);
        $this->assertSame('Default App', $billingSender['name']);
    }

    public function test_has_mail_purpose_trait_applies_correct_sender_to_mailable(): void
    {
        config(['mail.default' => 'resend']);

        $domain = EmailDomain::factory()->create(['domain' => 'system.test', 'is_active' => true]);

        EmailSender::factory()->create([
            'key' => MailPurpose::Auth,
            'email_domain_id' => $domain->id,
            'local_part' => 'security',
            'from_name' => 'Security Guard',
            'is_enabled' => true,
        ]);

        $mailable = new TestDummyMailable;
        $mailable->configurePurposeSender();

        $this->assertTrue($mailable->hasFrom('security@system.test', 'Security Guard'));
    }

    public function test_auth_notifications_are_should_queue_and_use_auth_purpose_sender(): void
    {
        config(['mail.default' => 'resend']);

        $domain = EmailDomain::factory()->create(['domain' => 'auth.test', 'is_active' => true]);

        EmailSender::factory()->create([
            'key' => MailPurpose::Auth,
            'email_domain_id' => $domain->id,
            'local_part' => 'login',
            'from_name' => 'Login System',
            'is_enabled' => true,
        ]);

        $verifyNotif = new VerifyEmailNotification;
        $resetNotif = new ResetPasswordNotification('sample-token');

        $this->assertInstanceOf(ShouldQueue::class, $verifyNotif);
        $this->assertInstanceOf(ShouldQueue::class, $resetNotif);

        $user = User::factory()->create(['email' => 'user@example.com']);

        $verifyMail = $verifyNotif->toMail($user);
        $resetMail = $resetNotif->toMail($user);

        $this->assertInstanceOf(VerifyEmailMail::class, $verifyMail);
        $this->assertInstanceOf(ResetPasswordMail::class, $resetMail);

        $this->assertTrue($verifyMail->hasTo('user@example.com'));
        $this->assertTrue($resetMail->hasTo('user@example.com'));

        $verifyMail->configurePurposeSender();
        $resetMail->configurePurposeSender();

        $this->assertSame('login@auth.test', $verifyMail->from[0]['address']);
        $this->assertSame('Login System', $verifyMail->from[0]['name']);

        $this->assertSame('login@auth.test', $resetMail->from[0]['address']);
        $this->assertSame('Login System', $resetMail->from[0]['name']);

        // The app user created above (type "app", distinct FRONTEND_URL configured
        // in this test env) resolves through UrlResolver's frontend/API branch —
        // the dedicated api.v1.verification.verify route (see routes/api/v1.php)
        // now that it's registered, not the old panel /verify-email/{id}/{hash} page.
        $this->assertStringContainsString('email/verify', $verifyMail->render());
        $this->assertStringContainsString('reset-password', $resetMail->render());
    }

    public function test_user_model_triggers_custom_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $user->sendEmailVerificationNotification();
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $user->sendPasswordResetNotification('test-token');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }
}
