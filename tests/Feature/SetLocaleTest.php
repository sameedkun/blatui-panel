<?php

namespace Tests\Feature;

use App\Livewire\Admin\Components\LanguageSwitcher;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    public function test_default_locale_is_en_without_cookie(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $this->assertSame('en', App::getLocale());
    }

    public function test_locale_is_set_from_cookie(): void
    {
        $response = $this->withCookie('locale', 'ur')->get('/');

        $response->assertStatus(200);
        $this->assertSame('ur', App::getLocale());
    }

    public function test_post_route_switches_locale_and_sets_cookie(): void
    {
        $response = $this->post(route('locale.switch', ['locale' => 'ur']));

        $response->assertCookie('locale', 'ur');
        $response->assertRedirect();
    }

    public function test_invalid_locale_returns_404(): void
    {
        $response = $this->post('/locale/fr');

        $response->assertStatus(404);
    }

    public function test_language_switcher_livewire_component_switches_locale(): void
    {
        Livewire::test(LanguageSwitcher::class)
            ->call('switchLocale', 'ur')
            ->assertRedirect();
    }
}
