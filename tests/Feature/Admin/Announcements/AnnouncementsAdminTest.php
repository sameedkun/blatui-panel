<?php

namespace Tests\Feature\Admin\Announcements;

use App\Enum\AnnouncementPushStatus;
use App\Enum\AnnouncementType;
use App\Jobs\Announcement\SendPushNotification;
use App\Livewire\Admin\Application\Announcement\Form;
use App\Livewire\Admin\Application\Announcement\Index;
use App\Models\Announcement;
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

class AnnouncementsAdminTest extends TestCase
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

    public function test_english_and_turkish_announcement_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('announcements', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('announcements', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_announcement_pages_and_status_dialog_use_the_request_locale(): void
    {
        $this->actingAsAdminWith([
            'panel.access-admin',
            'announcements.view',
            'announcements.create',
            'announcements.edit',
        ]);
        $announcement = Announcement::factory()->pending()->create(['title' => 'Bakım Duyurusu']);

        $indexResponse = $this->withCookie('locale', 'tr')->get(route('admin.announcements.index', ['status' => $announcement->id]));
        $indexResponse->assertOk();
        $indexResponse->assertSee('<title>'.__('announcements.title').' — '.config('app.name').'</title>', false);
        $indexResponse->assertSee(__('announcements.subtitle'));
        $indexResponse->assertSee(__('announcements.dialogs.status_title'));
        $indexResponse->assertSee(__('announcements.dialogs.watching'));
        $indexResponse->assertSee(__('announcements.fields.onesignal_id'));

        $createResponse = $this->withCookie('locale', 'tr')->get(route('admin.announcements.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('<title>'.__('announcements.form.create_title').' — '.config('app.name').'</title>', false);
        $createResponse->assertSee(__('announcements.form.create_description'));

        $editResponse = $this->withCookie('locale', 'tr')->get(route('admin.announcements.edit', $announcement));
        $editResponse->assertOk();
        $editResponse->assertSee('<title>'.__('announcements.form.edit_title').' — '.config('app.name').'</title>', false);
        $editResponse->assertSee(__('announcements.form.edit_description'));
    }

    public function test_announcement_validation_enum_labels_queue_feedback_and_toasts_use_the_active_locale(): void
    {
        App::setLocale('tr');
        Queue::fake();
        $this->actingAsAdminWith([
            'announcements.view',
            'announcements.create',
            'announcements.edit',
            'announcements.delete',
        ]);

        Livewire::test(Form::class)
            ->set('type', 'invalid')
            ->call('save')
            ->assertHasErrors(['title' => 'required', 'message' => 'required', 'type'])
            ->assertSee(__('announcements.validation.title_required'))
            ->assertSee(__('announcements.validation.message_required'))
            ->assertSee(__('announcements.validation.type_invalid'));

        Livewire::test(Form::class)
            ->set('title', 'Yeni Özellik')
            ->set('message', 'Yeni özellik artık kullanılabilir.')
            ->set('type', AnnouncementType::Announcement->value)
            ->set('sendNow', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.announcements.index'));

        $this->assertSame(__('announcements.toasts.created_draft'), session('toast.title'));

        $announcement = Announcement::where('title', 'Yeni Özellik')->firstOrFail();

        Livewire::test(Index::class)
            ->call('resend', $announcement->id)
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('announcements.toasts.push_queued', ['title' => $announcement->title]),
            )
            ->call('confirmDelete', $announcement->id)
            ->call('delete')
            ->assertDispatched(
                'toast',
                type: 'success',
                title: __('announcements.toasts.deleted', ['title' => $announcement->title]),
            );

        Queue::assertPushed(SendPushNotification::class);
        $this->assertSame(__('enums.announcement_push_status.Pending'), AnnouncementPushStatus::Pending->label());
        $this->assertSame(__('enums.announcement_type.Announcement'), AnnouncementType::Announcement->label());
        $this->assertModelMissing($announcement);
    }
}
