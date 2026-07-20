<?php

namespace Tests\Feature;

use App\Enum\MailPurpose;
use App\Livewire\Admin\Management\Users\Show;
use App\Livewire\Admin\Settings\Mail as MailSettings;
use App\Models\EmailDomain;
use App\Models\EmailSender;
use App\Models\SmtpSetting;
use App\Models\User;
use App\Support\ActivityPresenter;
use Database\Seeders\EmailSendersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Settings > Mail page logs every mutation under the single "setting"
 * module, so ActivityPresenter is what has to tell an SMTP change apart from
 * a domain add/delete or a sender update when scanning the audit trail.
 */
class ActivityPresenterMailTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    /** @return array{label: string, value: string|array<int, string>}|null */
    private function findRow(array $rows, string $label): ?array
    {
        return collect($rows)->firstWhere('label', $label);
    }

    public function test_smtp_save_presents_a_distinct_title(): void
    {
        config(['mail.default' => 'smtp']);
        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            ->set('smtp_host', 'smtp.example.com')
            ->set('smtp_port', '587')
            ->set('smtp_username', 'mailer')
            ->set('smtp_password', 'secret')
            ->set('smtp_from_address', 'noreply@example.com')
            ->set('smtp_from_name', 'Example')
            ->call('save');

        $activity = Activity::where('event', 'updated')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity);

        $this->assertSame('SMTP Settings Updated', $presented['title']);
    }

    public function test_domain_created_presents_a_distinct_title_and_shows_the_domain(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();

        Livewire::test(MailSettings::class)
            ->set('domain_name', 'Transactional')
            ->set('domain_domain', 'mail.example.com')
            ->call('saveDomain');

        $activity = Activity::where('event', 'created')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity);

        $this->assertSame('Sending Domain Added', $presented['title']);
        $this->assertSame('mail.example.com', $this->findRow($presented['rows'], 'Domain')['value'] ?? null);
    }

    public function test_domain_deleted_presents_a_distinct_title(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();
        $domain = EmailDomain::factory()->create(['domain' => 'old.example.com']);

        Livewire::test(MailSettings::class)
            ->call('confirmDeleteDomain', $domain->id)
            ->call('deleteDomain');

        $activity = Activity::where('event', 'deleted')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity);

        $this->assertSame('Sending Domain Removed', $presented['title']);
    }

    public function test_sender_update_presents_a_distinct_title_and_shows_the_purpose(): void
    {
        config(['mail.default' => 'resend']);
        $this->actingAsSuperAdmin();
        $this->seed(EmailSendersSeeder::class);
        $sender = EmailSender::query()->where('key', MailPurpose::Auth)->firstOrFail();

        Livewire::test(MailSettings::class)
            ->call('openSenderDialog', $sender->id)
            ->set('sender_from_name', 'New Name')
            ->call('saveSender');

        $activity = Activity::where('event', 'updated')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity);

        $this->assertSame('Mail Purpose Updated', $presented['title']);
        $this->assertSame('Auth', $this->findRow($presented['rows'], 'Purpose')['value'] ?? null);
    }

    public function test_test_email_presents_a_distinct_title_and_shows_the_recipient(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->actingAsSuperAdmin();
        SmtpSetting::query()->create([
            'host' => 'smtp.example.com', 'port' => 587, 'username' => 'mailer',
            'password' => 'secret', 'from_address' => 'noreply@example.com', 'from_name' => 'Example',
        ]);

        Livewire::test(MailSettings::class)
            ->set('test_email_address', 'someone@example.com')
            ->call('sendTestEmail');

        $activity = Activity::where('event', 'sent')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity);

        $this->assertSame('Test Email Sent', $presented['title']);
        $this->assertSame('someone@example.com', $this->findRow($presented['rows'], 'Sent to')['value'] ?? null);
    }

    /** A generic User "Profile Updated" title must be untouched by the new setting-area branch. */
    public function test_non_setting_activities_are_unaffected(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();

        Livewire::test(Show::class, ['user' => $user])
            ->call('openBanDialog', $user->id)
            ->set('banReason', 'spam')
            ->call('confirmBan');

        $activity = Activity::where('event', 'banned')->latest('id')->firstOrFail();
        $presented = ActivityPresenter::present($activity, $user);

        $this->assertSame('Account Banned', $presented['title']);
    }
}
