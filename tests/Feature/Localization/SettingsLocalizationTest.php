<?php

namespace Tests\Feature\Localization;

use App\Enum\MailPurpose;
use App\Enum\PolicyType;
use App\Livewire\Admin\Settings\General;
use App\Livewire\Admin\Settings\Mail as MailSettings;
use App\Livewire\Admin\Settings\Policies;
use App\Mail\TestMail;
use App\Models\Policy;
use App\Models\User;
use Database\Seeders\EmailSendersSeeder;
use Database\Seeders\PoliciesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(config('panel.super_admin_role'));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_english_and_turkish_settings_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('settings', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('settings', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_general_page_shell_and_browser_title_use_the_request_locale(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->withCookie('locale', 'tr')->get(route('admin.settings.general'));

        $response->assertOk();
        $response->assertSee('<title>'.__('settings.pages.general_title').' — '.config('app.name').'</title>', false);
        $response->assertSee(__('settings.subtitle'));
        $response->assertSee(__('settings.tabs.general'));
        $response->assertSee(__('settings.tabs.mail'));
        $response->assertSee(__('settings.tabs.policies'));
        $response->assertSee(__('settings.general.site_name'));
        $response->assertSee(__('settings.actions.save_general'));
    }

    public function test_smtp_and_resend_views_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();

        config(['mail.default' => 'smtp']);
        Livewire::test(MailSettings::class)
            ->assertSee(__('settings.pages.mail_title'))
            ->assertSee(__('settings.mail.smtp.encryption'))
            ->assertSee(__('settings.mail.smtp.password_description'))
            ->assertSee(__('settings.mail.test.title'));

        config(['mail.default' => 'resend']);
        $this->seed(EmailSendersSeeder::class);

        Livewire::test(MailSettings::class)
            ->assertSee(__('settings.mail.domains.title'))
            ->assertSee(__('settings.mail.senders.title'))
            ->assertSee(MailPurpose::Auth->label())
            ->call('openDomainDialog')
            ->assertSee(__('settings.mail.domains.add_title'))
            ->assertSee(__('settings.mail.domains.default_description'))
            ->assertSee(__('settings.mail.domains.delete_title'))
            ->assertSee(__('settings.mail.senders.edit_title'));
    }

    public function test_policies_and_version_dialog_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->seed(PoliciesSeeder::class);
        $this->actingAsSuperAdmin();

        Livewire::test(Policies::class)
            ->assertSee(__('settings.pages.policies_title'))
            ->assertSee(PolicyType::Privacy->label())
            ->assertSee(__('settings.policies.data_retention'))
            ->assertSee(__('settings.policies.version_history'))
            ->call(
                'viewVersion',
                Policy::where('key', PolicyType::Privacy->value)
                    ->firstOrFail()
                    ->activeVersion()
                    ->firstOrFail()
                    ->id,
            )
            ->assertSee(__('settings.policies.published'))
            ->assertSee(__('settings.policies.current_version'))
            ->assertSee(__('settings.actions.close'));
    }

    public function test_settings_validation_and_toasts_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsSuperAdmin();

        Livewire::test(General::class)
            ->set('site_name', '')
            ->call('save')
            ->assertHasErrors(['site_name' => 'required'])
            ->assertSee(__('settings.validation.site_name_required'));

        config(['mail.default' => 'resend']);

        Livewire::test(MailSettings::class)
            ->set('domain_name', '')
            ->set('domain_domain', '')
            ->call('saveDomain')
            ->assertHasErrors(['domain_name' => 'required', 'domain_domain' => 'required'])
            ->assertSee(__('settings.validation.domain_name_required'))
            ->assertSee(__('settings.validation.domain_required'));

        Livewire::test(MailSettings::class)
            ->set('domain_name', 'İşlemsel')
            ->set('domain_domain', 'mail.example.com')
            ->call('saveDomain')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('settings.toasts.domain_added'),
            );
    }

    public function test_test_email_content_uses_the_active_locale(): void
    {
        App::setLocale('tr');

        $mail = new TestMail('Örnek');

        $this->assertSame(
            __('settings.mail.test.subject', ['app' => config('app.name')]),
            $mail->envelope()->subject,
        );
        $this->assertSame(
            __('settings.mail.test.body', ['name' => 'Örnek']),
            $mail->content()->htmlString,
        );
    }
}
