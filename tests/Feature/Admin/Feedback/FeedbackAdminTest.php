<?php

namespace Tests\Feature\Admin\Feedback;

use App\Enum\FeedbackStatus;
use App\Enum\FeedbackType;
use App\Livewire\Admin\Application\Feedback\Show;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeedbackAdminTest extends TestCase
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

    public function test_english_and_turkish_feedback_translations_have_matching_keys(): void
    {
        $englishKeys = array_keys(Arr::dot(Lang::get('feedback', [], 'en')));
        $turkishKeys = array_keys(Arr::dot(Lang::get('feedback', [], 'tr')));

        sort($englishKeys);
        sort($turkishKeys);

        $this->assertSame($englishKeys, $turkishKeys);
    }

    public function test_feedback_pages_use_the_request_locale_in_content_and_browser_titles(): void
    {
        $this->actingAsAdminWith([
            'panel.access-admin',
            'feedback.view',
            'feedback.manage',
        ]);
        $feedback = Feedback::factory()->create(['subject' => null]);

        $indexResponse = $this->withCookie('locale', 'tr')->get(route('admin.feedback.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('<title>'.__('feedback.title').' — '.config('app.name').'</title>', false);
        $indexResponse->assertSee(__('feedback.subtitle'));
        $indexResponse->assertSee(__('feedback.filters.search'));

        $showResponse = $this->withCookie('locale', 'tr')->get(route('admin.feedback.show', $feedback));
        $showResponse->assertOk();
        $showResponse->assertSee(
            '<title>'.__('feedback.title').' — '.__('feedback.show.feedback_number', ['id' => $feedback->id]).' — '.config('app.name').'</title>',
            false,
        );
        $showResponse->assertSee(__('feedback.show.notes_title'));
        $showResponse->assertSee(__('feedback.show.controls_title'));
    }

    public function test_feedback_validation_enum_labels_and_action_toasts_use_the_active_locale(): void
    {
        App::setLocale('tr');
        $this->actingAsAdminWith(['feedback.view', 'feedback.manage']);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New]);

        $component = Livewire::test(Show::class, ['feedback' => $feedback])
            ->set('adminNotes', Str::repeat('a', 5001))
            ->call('saveNotes')
            ->assertHasErrors(['adminNotes' => 'max'])
            ->assertSee(__('feedback.validation.admin_notes_max', ['max' => 5000]))
            ->set('adminNotes', 'Yönetici inceleme notu.')
            ->call('saveNotes')
            ->assertDispatched('toast', type: 'success', title: __('feedback.toasts.notes_saved'))
            ->call('markAsRead')
            ->assertDispatched('toast', type: 'success', title: __('feedback.toasts.marked_read'))
            ->call('resolve')
            ->assertDispatched('toast', type: 'success', title: __('feedback.toasts.resolved'))
            ->call('ignore')
            ->assertDispatched('toast', type: 'success', title: __('feedback.toasts.ignored'))
            ->call('reopen')
            ->assertDispatched('toast', type: 'success', title: __('feedback.toasts.reopened'));

        $component->assertSee(FeedbackStatus::Read->label());
        $this->assertSame(__('enums.feedback_status.Read'), FeedbackStatus::Read->label());
        $this->assertSame(__('enums.feedback_type.Feature'), FeedbackType::Feature->label());
    }
}
