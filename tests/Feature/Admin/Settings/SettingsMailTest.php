<?php

namespace Tests\Feature;

use App\Enum\MailPurpose;
use App\Livewire\Admin\Settings\Mail as MailSettings;
use App\Mail\TestMail;
use App\Models\EmailDomain;
use App\Models\EmailSender;
use App\Models\SmtpSetting;
use App\Models\User;
use Database\Seeders\EmailSendersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SettingsMailTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    /** Staff granted exactly the given abilities (plus panel access). */
    private function staffWith(array $abilities): User
    {
        foreach (['panel.access-admin', 'settings.view', 'settings.mail.view', 'settings.mail.edit'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $staff = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $staff->givePermissionTo(array_merge(['panel.access-admin'], $abilities));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    public function test_page_403s_without_settings_view(): void
    {
        $this->actingAs($this->staffWith([])); // panel access only

        $this->get(route('admin.settings.mail'))->assertForbidden();
    }

    public function test_parent_settings_view_grants_mail_view_access(): void
    {
        $this->actingAs($this->staffWith(['settings.view']));

        $this->get(route('admin.settings.mail'))->assertOk();
    }

    public function test_smtp_save_is_forbidden_without_settings_mail_edit(): void
    {
        config(['mail.default' => 'smtp']);
        $this->actingAs($this->staffWith(['settings.view', 'settings.mail.view'])); // no edit

        Livewire::test(MailSettings::class)->call('save')->assertForbidden();
    }

    public function test_smtp_password_is_required_on_the_first_ever_save(): void
    {
        config(['mail.default' => 'smtp']);
        $this->actingAsSuperAdmin();
        // No SmtpSetting row exists yet — `password` is NOT NULL on the table,
        // so leaving it blank must fail validation, not crash the insert.

        Livewire::test(MailSettings::class)
            ->set('smtp_host', 'smtp.example.com')
            ->set('smtp_port', '587')
            ->set('smtp_username', 'mailer')
            ->set('smtp_from_address', 'noreply@example.com')
            ->set('smtp_from_name', 'Example')
            ->call('save')
            ->assertHasErrors(['smtp_password' => 'required']);

        $this->assertDatabaseCount('smtp_settings', 0);
    }

    public function test_smtp_settings_save_persists_and_encrypts_the_password(): void
    {
        config(['mail.default' => 'smtp']);
        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            ->set('smtp_host', 'smtp.example.com')
            ->set('smtp_port', '2525')
            ->set('smtp_encryption', 'tls')
            ->set('smtp_username', 'mailer')
            ->set('smtp_password', 'super-secret')
            ->set('smtp_from_address', 'noreply@example.com')
            ->set('smtp_from_name', 'Example')
            ->call('save');

        $smtp = SmtpSetting::current();
        $this->assertSame('smtp.example.com', $smtp->host);
        $this->assertSame(2525, $smtp->port);
        $this->assertSame('super-secret', $smtp->password);
        $this->assertDatabaseMissing('smtp_settings', ['password' => 'super-secret']);
    }

    public function test_smtp_save_keeps_the_existing_password_when_left_blank(): void
    {
        config(['mail.default' => 'smtp']);
        $this->actingAsSuperAdmin();

        SmtpSetting::query()->create([
            'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'mailer', 'password' => 'original-secret',
            'from_address' => 'noreply@example.com', 'from_name' => 'Example',
        ]);

        Livewire::test(MailSettings::class)
            ->set('smtp_host', 'smtp.updated.com')
            ->call('save');

        $this->assertSame('original-secret', SmtpSetting::current()->password);
        $this->assertSame('smtp.updated.com', SmtpSetting::current()->host);
    }

    public function test_domain_actions_are_forbidden_without_settings_mail_edit(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAs($this->staffWith(['settings.view', 'settings.mail.view']));

        Livewire::test(MailSettings::class)->call('openDomainDialog')->assertForbidden();
    }

    public function test_domain_can_be_added(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            ->set('domain_name', 'Transactional')
            ->set('domain_domain', 'mail.example.com')
            ->set('domain_is_active', true)
            ->call('saveDomain');

        $this->assertDatabaseHas('email_domains', ['domain' => 'mail.example.com', 'name' => 'Transactional']);
    }

    public function test_domain_domain_must_be_unique(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();
        EmailDomain::factory()->create(['domain' => 'mail.example.com']);

        Livewire::test(MailSettings::class)
            ->set('domain_name', 'Another')
            ->set('domain_domain', 'mail.example.com')
            ->call('saveDomain')
            ->assertHasErrors(['domain_domain' => 'unique']);
    }

    public function test_marking_a_domain_default_assigns_it_to_the_default_purpose_sender(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();
        $this->seed(EmailSendersSeeder::class);

        Livewire::test(MailSettings::class)
            ->set('domain_name', 'Primary')
            ->set('domain_domain', 'mail.example.com')
            ->set('domain_is_default', true)
            ->set('domain_is_active', true)
            ->call('saveDomain');

        $domain = EmailDomain::query()->where('domain', 'mail.example.com')->firstOrFail();
        $defaultSender = EmailSender::query()->where('key', MailPurpose::Default)->firstOrFail();

        $this->assertSame($domain->id, $defaultSender->email_domain_id);

        // Every other purpose without its own domain now resolves through it.
        $result = EmailSender::resolve(MailPurpose::Notifications);
        $this->assertSame('noreply@mail.example.com', $result['address']);
    }

    public function test_only_one_domain_can_be_marked_default_at_a_time(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();
        $existingDefault = EmailDomain::factory()->create(['domain' => 'old.example.com', 'is_default' => true]);

        Livewire::test(MailSettings::class)
            ->set('domain_name', 'New Primary')
            ->set('domain_domain', 'new.example.com')
            ->set('domain_is_default', true)
            ->call('saveDomain');

        $this->assertFalse($existingDefault->fresh()->is_default);
        $this->assertTrue(EmailDomain::query()->where('domain', 'new.example.com')->firstOrFail()->is_default);
    }

    public function test_domain_can_be_deleted(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();
        $domain = EmailDomain::factory()->create();

        Livewire::test(MailSettings::class)
            ->call('confirmDeleteDomain', $domain->id)
            ->call('deleteDomain');

        $this->assertDatabaseMissing('email_domains', ['id' => $domain->id]);
    }

    public function test_sender_can_be_assigned_a_domain_and_persists(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();
        $this->seed(EmailSendersSeeder::class);
        $domain = EmailDomain::factory()->create(['domain' => 'auth.example.com', 'is_active' => true]);
        $sender = EmailSender::query()->where('key', MailPurpose::Auth)->firstOrFail();

        Livewire::test(MailSettings::class)
            ->call('openSenderDialog', $sender->id)
            // The domain <option> must carry the domain's id as its value, not
            // its name — a plain-list-shaped `options` array silently uses the
            // label as the value instead and breaks the select entirely.
            ->assertSeeHtml('value="'.$domain->id.'"')
            ->set('sender_email_domain_id', (string) $domain->id)
            ->set('sender_local_part', 'login')
            ->set('sender_from_name', 'Example Auth')
            ->call('saveSender');

        $sender->refresh();
        $this->assertSame($domain->id, $sender->email_domain_id);
        $this->assertSame('login', $sender->local_part);
        $this->assertSame('login@auth.example.com', $sender->fromAddress);
    }

    public function test_sender_save_is_forbidden_without_settings_mail_edit(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAs($this->staffWith(['settings.view', 'settings.mail.view']));
        $this->seed(EmailSendersSeeder::class);
        $sender = EmailSender::query()->where('key', MailPurpose::Default)->firstOrFail();

        Livewire::test(MailSettings::class)->call('openSenderDialog', $sender->id)->assertForbidden();
    }

    public function test_test_email_is_forbidden_without_settings_mail_edit(): void
    {
        config(['mail.default' => 'smtp']);
        $this->actingAs($this->staffWith(['settings.view', 'settings.mail.view']));

        Livewire::test(MailSettings::class)
            ->set('test_email_address', 'someone@example.com')
            ->call('sendTestEmail')
            ->assertForbidden();
    }

    public function test_test_email_requires_a_valid_address(): void
    {
        config(['mail.default' => 'smtp']);
        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            ->set('test_email_address', 'not-an-email')
            ->call('sendTestEmail')
            ->assertHasErrors(['test_email_address' => 'email']);
    }

    public function test_test_email_sends_via_smtp_using_the_saved_from_address(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->actingAsSuperAdmin();
        SmtpSetting::query()->create([
            'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls',
            'username' => 'mailer', 'password' => 'secret',
            'from_address' => 'noreply@example.com', 'from_name' => 'Example',
        ]);

        Livewire::test(MailSettings::class)
            ->set('test_email_address', 'someone@example.com')
            ->call('sendTestEmail');

        Mail::assertSent(TestMail::class, function (TestMail $mail) {
            return $mail->hasTo('someone@example.com')
                && $mail->from[0]['address'] === 'noreply@example.com';
        });
    }

    public function test_test_email_sends_via_resend_using_the_selected_purposes_from_address(): void
    {
        Mail::fake();
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();
        $this->seed(EmailSendersSeeder::class);
        $domain = EmailDomain::factory()->create(['domain' => 'auth.example.com', 'is_active' => true]);
        EmailSender::query()->where('key', MailPurpose::Auth)->update([
            'email_domain_id' => $domain->id,
            'local_part' => 'login',
        ]);

        Livewire::test(MailSettings::class)
            ->set('test_email_address', 'someone@example.com')
            ->set('test_email_purpose', MailPurpose::Auth->value)
            ->call('sendTestEmail');

        Mail::assertSent(TestMail::class, function (TestMail $mail) {
            return $mail->hasTo('someone@example.com')
                && $mail->from[0]['address'] === 'login@auth.example.com';
        });
    }

    public function test_test_email_fails_gracefully_when_no_from_address_is_configured(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->actingAsSuperAdmin();
        // No SmtpSetting row at all yet.

        Livewire::test(MailSettings::class)
            ->set('test_email_address', 'someone@example.com')
            ->call('sendTestEmail')
            ->assertDispatched('toast', type: 'error');

        Mail::assertNothingSent();
    }
}
