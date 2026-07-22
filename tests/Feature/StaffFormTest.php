<?php

namespace Tests\Feature;

use App\Livewire\Admin\Administration\Staff\Form;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffFormTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']));
        $this->actingAs($admin);

        return $admin;
    }

    private function assignableRole(): Role
    {
        return Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    }

    public function test_creating_staff_with_verification_required_sends_a_verification_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $role = $this->assignableRole();

        Livewire::test(Form::class)
            ->set('name', 'New Staffer')
            ->set('email', 'staffer@example.com')
            ->set('password', 'a-real-password')
            ->set('roles', [$role->name])
            ->set('sendVerificationEmail', true)
            ->call('save');

        $user = User::where('email', 'staffer@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_creating_staff_without_verification_required_auto_verifies_and_sends_no_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $role = $this->assignableRole();

        Livewire::test(Form::class)
            ->set('name', 'New Staffer')
            ->set('email', 'staffer@example.com')
            ->set('password', 'a-real-password')
            ->set('roles', [$role->name])
            ->set('sendVerificationEmail', false)
            ->call('save');

        $user = User::where('email', 'staffer@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    public function test_changing_a_staff_members_email_sends_a_verification_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $role = $this->assignableRole();
        $staff = User::factory()->create(['type' => 'staff', 'email' => 'old@example.com', 'email_verified_at' => now()]);
        $staff->assignRole($role);

        Livewire::test(Form::class, ['user' => $staff])
            ->set('email', 'new@example.com')
            ->call('save');

        Notification::assertSentTo($staff, VerifyEmailNotification::class);
    }

    public function test_auto_verifying_a_staff_members_changed_email_sends_no_verification_email(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $role = $this->assignableRole();
        $staff = User::factory()->create(['type' => 'staff', 'email' => 'old@example.com', 'email_verified_at' => now()]);
        $staff->assignRole($role);

        Livewire::test(Form::class, ['user' => $staff])
            ->set('email', 'new@example.com')
            ->set('autoVerifyChangedEmail', true)
            ->call('save');

        Notification::assertNotSentTo($staff, VerifyEmailNotification::class);
        $this->assertNotNull($staff->fresh()->email_verified_at);
    }

    public function test_forcing_a_staff_password_reset_sends_a_reset_link(): void
    {
        Notification::fake();
        $this->actingAsSuperAdmin();
        $role = $this->assignableRole();
        $staff = User::factory()->create(['type' => 'staff']);
        $staff->assignRole($role);

        Livewire::test(Form::class, ['user' => $staff])
            ->set('forcePasswordReset', true)
            ->call('save');

        Notification::assertSentTo($staff, ResetPasswordNotification::class);
    }
}
