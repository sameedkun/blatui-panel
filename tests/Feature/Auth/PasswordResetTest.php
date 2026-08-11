<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\PasswordReset;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_are_redirected_away_from_the_reset_page(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->actingAs(User::factory()->create())
            ->get(route('password.reset', ['token' => $token]))
            ->assertRedirect('/dashboard');
    }

    public function test_it_resets_the_password_with_a_valid_token(): void
    {
        Event::fake([PasswordResetEvent::class]);

        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        Livewire::test(PasswordReset::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('resetPassword')
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        Event::assertDispatched(PasswordResetEvent::class);
    }

    public function test_it_bumps_password_changed_at_after_a_successful_reset(): void
    {
        $user = User::factory()->create(['password_changed_at' => null]);
        $token = Password::broker()->createToken($user);

        Livewire::test(PasswordReset::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('resetPassword');

        $this->assertNotNull($user->fresh()->password_changed_at);
    }

    public function test_it_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();

        Livewire::test(PasswordReset::class, ['token' => 'invalid-token'])
            ->set('email', $user->email)
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('resetPassword')
            ->assertHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
