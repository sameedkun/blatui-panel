<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\UrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UrlResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/verify-email/{id}/{hash}', fn () => null)->name('verification.verify');
        Route::get('/reset-password/{token}', fn () => null)->name('password.reset');
    }

    private function queryParam(string $url, string $key): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query[$key];
    }

    public function test_staff_get_panel_urls_in_auto_mode(): void
    {
        config(['panel.auth_url_mode' => 'auto']);

        $staff = User::factory()->create(['type' => 'staff']);
        $resolver = new UrlResolver;

        $verificationUrl = $resolver->verificationUrl($staff);
        $resetUrl = $resolver->passwordResetUrl($staff, 'panel-token');

        $this->assertStringContainsString('verify-email/'.$staff->external_id, $verificationUrl);
        $this->assertStringContainsString('signature=', $verificationUrl);

        $this->assertStringContainsString('reset-password/panel-token', $resetUrl);
        $this->assertSame($staff->email, Crypt::decryptString($this->queryParam($resetUrl, 'email')));
    }

    public function test_app_users_get_panel_urls_in_auto_mode_when_no_distinct_frontend_is_configured(): void
    {
        // FRONTEND_URL isn't set, so panel.frontend_url falls back to APP_URL — there is
        // nowhere else to send an app user, so 'auto' must not divert them to a broken link.
        config(['panel.auth_url_mode' => 'auto', 'panel.frontend_url' => config('app.url')]);

        $appUser = User::factory()->create(['type' => 'app']);
        $resolver = new UrlResolver;

        $verificationUrl = $resolver->verificationUrl($appUser);
        $resetUrl = $resolver->passwordResetUrl($appUser, 'app-token');

        $this->assertStringContainsString('verify-email/'.$appUser->external_id, $verificationUrl);
        $this->assertStringContainsString('signature=', $verificationUrl);

        $this->assertStringContainsString('reset-password/app-token', $resetUrl);
        $this->assertSame($appUser->email, Crypt::decryptString($this->queryParam($resetUrl, 'email')));
    }

    public function test_app_users_get_frontend_urls_in_auto_mode_when_a_distinct_frontend_is_configured(): void
    {
        // routes/api/v1.php registers the real `api.v1.verification.verify` route
        // for every request (including tests), so there's no "route missing"
        // fallback to exercise here anymore — an app user with a distinct
        // frontend configured gets routed straight to it.
        config(['panel.auth_url_mode' => 'auto', 'panel.frontend_url' => 'https://quixure.com']);

        $appUser = User::factory()->create(['type' => 'app']);
        $resolver = new UrlResolver;

        $verificationUrl = $resolver->verificationUrl($appUser);
        // A frontend-hosted landing page (query-param shape), never the raw
        // API route — and keyed by external_id, never the numeric PK.
        $this->assertStringStartsWith('https://quixure.com/email/verify?', $verificationUrl);
        $this->assertSame($appUser->external_id, $this->queryParam($verificationUrl, 'id'));
        $this->assertStringContainsString('signature=', $verificationUrl);

        $resetUrl = $resolver->passwordResetUrl($appUser, 'app-token');
        $this->assertStringStartsWith('https://quixure.com/reset-password?', $resetUrl);
        $this->assertStringContainsString('token=app-token', $resetUrl);
        $this->assertSame($appUser->email, Crypt::decryptString($this->queryParam($resetUrl, 'email')));
    }

    public function test_verification_uses_the_dedicated_api_route_in_explicit_frontend_mode(): void
    {
        config(['panel.auth_url_mode' => 'frontend', 'panel.frontend_url' => 'https://quixure.com']);

        $appUser = User::factory()->create(['type' => 'app']);
        $resolver = new UrlResolver;

        $verificationUrl = $resolver->verificationUrl($appUser);

        $this->assertStringStartsWith('https://quixure.com/email/verify?', $verificationUrl);
        $this->assertSame($appUser->external_id, $this->queryParam($verificationUrl, 'id'));
        $this->assertStringContainsString('signature=', $verificationUrl);
    }

    public function test_explicit_mode_overrides_user_type(): void
    {
        config(['panel.auth_url_mode' => 'panel']);

        $appUser = User::factory()->create(['type' => 'app']);
        $resolver = new UrlResolver;

        $verificationUrl = $resolver->verificationUrl($appUser);

        $this->assertStringContainsString('verify-email/'.$appUser->external_id, $verificationUrl);
    }
}
