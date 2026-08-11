<?php

namespace Tests\Feature\Admin\Notifications;

use App\Enum\NotificationPushStatus;
use App\Enum\NotificationType;
use App\Jobs\Notification\SendPushNotification;
use App\Livewire\Admin\Application\Notification\Form;
use App\Livewire\Admin\Application\Notification\Index;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdminWith(array $permissions): User
    {
        $admin = User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        $role = Role::firstOrCreate(['name' => 'test-role-'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
        $admin->assignRole($role);

        $this->actingAs($admin);

        return $admin;
    }

    public function test_english_and_turkish_notification_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('notifications', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('notifications', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_notification_pages_and_status_dialog_use_the_request_locale(): void
    {
        $this->actingAsAdminWith([
            'panel.access-admin',
            'notifications.view',
            'notifications.create',
            'notifications.edit',
        ]);
        $notification = Notification::factory()->pending()->create(['title' => 'Bakım Duyurusu']);

        $indexResponse = $this->withCookie('locale', 'tr')->get(route('admin.notifications.index', ['status' => $notification->id]));
        $indexResponse->assertOk();
        $indexResponse->assertSee('<title>'.__('notifications.title').' — '.config('app.name').'</title>', false);
        $indexResponse->assertSee(__('notifications.subtitle'));
        $indexResponse->assertSee(__('notifications.dialogs.status_title'));
        $indexResponse->assertSee(__('notifications.dialogs.watching'));
        $indexResponse->assertSee(__('notifications.fields.onesignal_id'));

        $createResponse = $this->withCookie('locale', 'tr')->get(route('admin.notifications.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('<title>'.__('notifications.form.create_title').' — '.config('app.name').'</title>', false);
        $createResponse->assertSee(__('notifications.form.create_description'));

        $editResponse = $this->withCookie('locale', 'tr')->get(route('admin.notifications.edit', $notification));
        $editResponse->assertOk();
        $editResponse->assertSee('<title>'.__('notifications.form.edit_title').' — '.config('app.name').'</title>', false);
        $editResponse->assertSee(__('notifications.form.edit_description'));
    }

    public function test_notification_validation_enum_labels_queue_feedback_and_toasts_use_the_active_locale(): void
    {
        App::setLocale('tr');
        Queue::fake();
        $this->actingAsAdminWith([
            'notifications.view',
            'notifications.create',
            'notifications.edit',
            'notifications.delete',
        ]);

        Livewire::test(Form::class)
            ->set('type', 'invalid')
            ->call('save')
            ->assertHasErrors(['title' => 'required', 'message' => 'required', 'type'])
            ->assertSee(__('notifications.validation.title_required'))
            ->assertSee(__('notifications.validation.message_required'))
            ->assertSee(__('notifications.validation.type_invalid'));

        Livewire::test(Form::class)
            ->set('title', 'Yeni Özellik')
            ->set('message', 'Yeni özellik artık kullanılabilir.')
            ->set('type', NotificationType::Announcement->value)
            ->set('sendNow', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertSame(__('notifications.toasts.created_draft'), session('toast.title'));

        $notification = Notification::where('title', 'Yeni Özellik')->firstOrFail();

        Livewire::test(Index::class)
            ->call('resend', $notification->id)
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('notifications.toasts.push_queued', ['title' => $notification->title]),
            )
            ->call('confirmDelete', $notification->id)
            ->call('delete')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('notifications.toasts.deleted', ['title' => $notification->title]),
            );

        Queue::assertPushed(SendPushNotification::class);
        $this->assertSame(__('enums.notification_push_status.Pending'), NotificationPushStatus::Pending->label());
        $this->assertSame(__('enums.notification_type.Announcement'), NotificationType::Announcement->label());
        $this->assertModelMissing($notification);
    }
}
