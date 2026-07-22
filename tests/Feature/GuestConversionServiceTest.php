<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Services\GuestConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class GuestConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): GuestConversionService
    {
        return app(GuestConversionService::class);
    }

    public function test_converting_a_non_guest_is_rejected(): void
    {
        $appUser = User::factory()->app()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->convertByAdmin($appUser, 'new@example.com');
    }

    public function test_converting_a_banned_guest_is_rejected(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => now()]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->convertByAdmin($guest, 'new@example.com');
    }

    public function test_converting_to_a_duplicate_email_is_rejected(): void
    {
        User::factory()->app()->create(['email' => 'taken@example.com']);
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        $this->expectException(ValidationException::class);

        $this->service()->convertByAdmin($guest, 'taken@example.com');
    }

    public function test_convert_by_self_sets_the_chosen_password_and_flips_type(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null, 'name' => 'Old Name']);

        $this->service()->convertBySelf($guest, 'new@example.com', 'a-real-password', 'New Name');

        $fresh = $guest->fresh();
        $this->assertTrue($fresh->isAppUser());
        $this->assertSame('new@example.com', $fresh->email);
        $this->assertSame('New Name', $fresh->name);
        $this->assertNull($fresh->email_verified_at);
        $this->assertTrue(Hash::check('a-real-password', $fresh->password));
        $this->assertSame($guest->id, $fresh->id);
    }

    public function test_convert_by_self_keeps_the_existing_name_when_none_given(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null, 'name' => 'Keep Me']);

        $this->service()->convertBySelf($guest, 'new@example.com', 'a-real-password');

        $this->assertSame('Keep Me', $guest->fresh()->name);
    }

    public function test_convert_by_admin_never_leaves_the_original_password_usable(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $originalHash = $guest->password;

        $this->service()->convertByAdmin($guest, 'new@example.com');

        $fresh = $guest->fresh();
        $this->assertTrue($fresh->isAppUser());
        $this->assertNotSame($originalHash, $fresh->password);
        $this->assertFalse(Hash::check('password', $fresh->password));
    }

    public function test_convert_logs_the_converted_activity_with_initiated_by(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null, 'email' => 'old@example.com']);

        $this->service()->convertByAdmin($guest, 'new@example.com');

        $row = Activity::where('subject_id', $guest->id)->where('event', 'converted')->firstOrFail();

        $this->assertSame('guest', $row->properties['module']);
        $this->assertSame('admin', $row->properties['initiated_by']);
        $this->assertSame('old@example.com', $row->properties['old_email']);
        $this->assertSame('new@example.com', $row->properties['new_email']);
        $this->assertFalse($row->properties['email_verified_by_admin']);
    }

    public function test_convert_by_admin_can_mark_the_email_as_verified(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        $this->service()->convertByAdmin($guest, 'new@example.com', null, true);

        $fresh = $guest->fresh();
        $this->assertNotNull($fresh->email_verified_at);

        $row = Activity::where('subject_id', $guest->id)->where('event', 'converted')->firstOrFail();
        $this->assertTrue($row->properties['email_verified_by_admin']);
    }

    public function test_convert_by_admin_defaults_to_unverified_email(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        $this->service()->convertByAdmin($guest, 'new@example.com');

        $this->assertNull($guest->fresh()->email_verified_at);
    }

    public function test_convert_by_self_sends_a_verification_email_for_the_new_address(): void
    {
        Notification::fake();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        $this->service()->convertBySelf($guest, 'new@example.com', 'a-real-password');

        Notification::assertSentTo($guest, VerifyEmailNotification::class);
    }

    public function test_convert_by_admin_sends_a_verification_email_when_not_marked_verified(): void
    {
        Notification::fake();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        $this->service()->convertByAdmin($guest, 'new@example.com');

        Notification::assertSentTo($guest, VerifyEmailNotification::class);
    }

    public function test_convert_by_admin_sends_no_verification_email_when_marked_verified(): void
    {
        Notification::fake();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        $this->service()->convertByAdmin($guest, 'new@example.com', null, true);

        Notification::assertNotSentTo($guest, VerifyEmailNotification::class);
    }

    public function test_convert_by_admin_always_sends_a_password_reset_link_regardless_of_verification(): void
    {
        Notification::fake();
        $guest = User::factory()->guest()->create(['banned_at' => null]);

        $this->service()->convertByAdmin($guest, 'new@example.com', null, true);

        Notification::assertSentTo($guest, ResetPasswordNotification::class);
    }

    public function test_merging_a_non_guest_is_rejected(): void
    {
        $appUser = User::factory()->app()->create();
        $destination = User::factory()->app()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->mergeByAdmin($appUser, $destination, 'Duplicate account');
    }

    public function test_merging_a_banned_guest_is_rejected(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => now()]);
        $destination = User::factory()->app()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->mergeByAdmin($guest, $destination, 'Duplicate account');
    }

    public function test_merge_destination_must_be_an_app_user(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $anotherGuest = User::factory()->guest()->create(['banned_at' => null]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->mergeByAdmin($guest, $anotherGuest, 'Duplicate account');
    }

    public function test_merge_requires_a_reason(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $destination = User::factory()->app()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->mergeByAdmin($guest, $destination, '   ');
    }

    public function test_merge_permanently_removes_the_guest_and_survives_as_the_destination(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $destination = User::factory()->app()->create();

        $result = $this->service()->mergeByAdmin($guest, $destination, 'Confirmed same person via support ticket');

        $this->assertSame($destination->id, $result->id);
        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
        $this->assertNotNull($destination->fresh());
    }

    public function test_merge_logs_the_merged_activity_with_the_reason(): void
    {
        $guest = User::factory()->guest()->create(['banned_at' => null]);
        $destination = User::factory()->app()->create();

        $this->service()->mergeByAdmin($guest, $destination, 'Confirmed same person via support ticket');

        $row = Activity::where('subject_id', $destination->id)->where('event', 'merged')->firstOrFail();

        $this->assertSame('user', $row->properties['module']);
        $this->assertSame('admin', $row->properties['initiated_by']);
        $this->assertSame($guest->id, $row->properties['guest_id']);
        $this->assertSame('Confirmed same person via support ticket', $row->properties['reason']);
    }
}
