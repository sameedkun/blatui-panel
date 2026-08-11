<?php

namespace Tests\Feature\Localization;

use App\Livewire\Admin\Components\LanguageSwitcher;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    public function test_supported_locale_cookie_is_applied_to_the_request(): void
    {
        // '/' always redirects (to login or the dashboard, based on auth state) since the
        // home-routing refactor, so this hits a guest route that actually renders instead.
        $this->withCookie('locale', 'tr')
            ->get('/login')
            ->assertOk();

        $this->assertSame('tr', App::getLocale());
    }

    public function test_locale_switch_route_persists_a_supported_locale(): void
    {
        $this->post(route('locale.switch', ['locale' => 'tr']))
            ->assertRedirect()
            ->assertCookie('locale', 'tr');
    }

    public function test_locale_switch_route_rejects_an_unsupported_locale(): void
    {
        $this->post(route('locale.switch', ['locale' => 'fr']))
            ->assertNotFound();
    }

    public function test_language_switcher_queues_the_locale_cookie_and_redirects(): void
    {
        Cookie::spy();

        Livewire::test(LanguageSwitcher::class)
            ->call('switchLocale', 'tr')
            ->assertRedirect();

        Cookie::shouldHaveReceived('queue')
            ->once()
            ->with('locale', 'tr', 60 * 24 * 365);
    }
}
