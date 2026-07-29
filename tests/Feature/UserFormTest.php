<?php

namespace Tests\Feature;

use App\Livewire\Admin\Management\Users\Form;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserFormTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    public function test_create_page_title_uses_the_request_locale(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->withCookie('locale', 'tr')->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertSee('<title>'.__('users.create_user').' — '.config('app.name').'</title>', false);
    }

    public function test_creating_a_user_without_auto_verify_sends_a_verification_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();

        Livewire::test(Form::class)
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('password', 'a-real-password')
            ->set('autoVerifyEmail', false)
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame([
            'type' => 'success',
            'title' => __('users.toasts.user_created', ['name' => $user->name]),
        ], session('toast'));
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_creating_a_user_with_auto_verify_sends_no_verification_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();

        Livewire::test(Form::class)
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('password', 'a-real-password')
            ->set('autoVerifyEmail', true)
            ->call('save');

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    public function test_changing_a_users_email_sends_a_verification_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

        Livewire::test(Form::class, ['user' => $user])
            ->set('email', 'new@example.com')
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        Notification::assertSentTo($user, VerifyEmailNotification::class);
        $this->assertSame([
            'type' => 'success',
            'title' => __('users.toasts.user_updated', ['name' => $user->name]),
        ], session('toast'));
    }

    public function test_auto_verifying_a_changed_email_sends_no_verification_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

        Livewire::test(Form::class, ['user' => $user])
            ->set('email', 'new@example.com')
            ->set('autoVerifyChangedEmail', true)
            ->call('save');

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_keeping_the_same_email_sends_no_verification_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create(['email' => 'same@example.com', 'email_verified_at' => now()]);

        Livewire::test(Form::class, ['user' => $user])
            ->set('name', 'Renamed')
            ->call('save');

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    public function test_forcing_a_password_reset_sends_a_reset_link(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();

        Livewire::test(Form::class, ['user' => $user])
            ->set('forcePasswordReset', true)
            ->call('save');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_not_forcing_a_password_reset_sends_no_reset_link(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $user = User::factory()->app()->create();

        Livewire::test(Form::class, ['user' => $user])
            ->set('name', 'Renamed')
            ->call('save');

        Notification::assertNotSentTo($user, ResetPasswordNotification::class);
    }
}
