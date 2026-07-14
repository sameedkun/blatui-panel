<?php

namespace App\Livewire\Admin\Management\Guests;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Guests\Concerns\HandlesGuestRowActions;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

/**
 * Read-focused profile page for a single guest — the trimmed counterpart to
 * {@see \App\Livewire\Admin\Management\Users\Show}. Guests never enter the
 * app-user grace-period deletion flow or email-verification lifecycle, so
 * both the tab set and the header actions are deliberately smaller here.
 *
 * Header actions reuse the shared {@see HandlesGuestRowActions} — a ban here
 * is byte-for-byte the same action (and audit-log row) as a ban from the index.
 */
#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use HandlesGuestRowActions;
    use HasShowTabs;
    use LogsAdminActivity;

    public function mount(User $user): void
    {
        abort_unless($user->isGuest(), 404);

        $this->initShow($user);
    }

    protected function indexRoute(): string
    {
        return 'admin.guests.index';
    }

    protected function title(): string
    {
        return $this->record->name;
    }

    /**
     * No Devices/Tickets here — guests don't have them. Subscriptions and
     * Activity stay "coming soon" placeholders, same as they are on the Users
     * profile today.
     */
    protected function tabs(): array
    {
        return [
            'overview' => [
                'label' => 'Overview',
                'icon' => 'user',
                'view' => 'livewire.admin.management.guests.profile.tabs.overview',
            ],
            'subscriptions' => $this->comingSoonTab('Subscriptions', 'credit-card', 'Subscriptions for this account will appear here once billing ships.'),
            'activity' => $this->comingSoonTab('Activity', 'activity', 'A full activity timeline for this account will appear here.'),
        ];
    }

    /**
     * Instant delete removes the record, so — unlike the index — the profile
     * has nowhere to stay and returns to the list. The deletion itself (and
     * its audit entry) still runs through {@see AccountDeletionService}; no
     * new logic here.
     */
    public function delete(AccountDeletionService $deletions)
    {
        $this->authorize('guests.delete');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->deletingId);
        $this->assertLifecycleState($guest, ['active']);

        $name = $guest->name;
        $deletions->purgeGuestByAdmin($guest);

        session()->flash('toast', ['type' => 'success', 'title' => "{$name} has been permanently deleted."]);

        return $this->redirect(route('admin.guests.index'));
    }

    /**
     * Force-delete also removes the record — same "nowhere to stay" reasoning
     * as {@see delete()} — so the profile redirects back to the list on success.
     */
    public function forceDelete()
    {
        $this->authorize('guests.force-delete');

        $guest = User::query()->guests()->withTrashed()->findOrFail($this->forceDeleteId);
        $this->assertLifecycleState($guest, ['trashed']);

        $name = $guest->name;

        $this->logActivity(ActivityModule::Guest, ActivityAction::ForceDeleted, $guest, ['user_id' => $guest->id, 'name' => $name]);

        $guest->forceDelete();

        session()->flash('toast', ['type' => 'success', 'title' => "{$name} has been permanently deleted."]);

        return $this->redirect(route('admin.guests.index'));
    }

    /**
     * Scaffolded — wiring point only. Toasts rather than silently doing
     * nothing, but does not yet perform the conversion.
     *
     * TODO: when built, this must flip `type` to App, decide what happens to
     * guest-only fields (google_id/apple_id stay, deletion_* stay unused),
     * and be logged as a staff-initiated account-type change.
     */
    public function convertToAppUser(): void
    {
        $this->toastInfo('Not yet available', 'Converting a guest to an app account is not implemented yet.');
    }

    /**
     * Summary cards under the header. Real values come from their own accessor
     * so a future module fills one in without touching the layout; unbuilt
     * metrics return null and render a "Coming soon" placeholder.
     *
     * @return array<int, array{label: string, icon: string, value: string|null}>
     */
    public function statCards(): array
    {
        return [
            ['label' => 'Subscriptions', 'icon' => 'credit-card', 'value' => null],
            ['label' => 'Activity', 'icon' => 'activity', 'value' => null],
            ['label' => 'Joined', 'icon' => 'calendar', 'value' => $this->record->registration_date?->format('M d, Y') ?? '—'],
        ];
    }

    public function render(): View
    {
        // Reflect any header action (ban, delete) on this same request.
        $this->refreshRecord();

        return view('livewire.admin.management.guests.show', [
            'stats' => $this->statCards(),
        ]);
    }

    /** @return array<string, mixed> */
    private function comingSoonTab(string $label, string $icon, string $description): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'view' => 'livewire.admin.management.users.profile.tabs.placeholder',
            'data' => fn (): array => ['label' => $label, 'icon' => $icon, 'description' => $description],
        ];
    }

    /** Pull fresh attributes so header badges reflect an action taken this request. */
    private function refreshRecord(): void
    {
        if ($fresh = $this->record->fresh()) {
            $this->record = $fresh;
        }
    }
}
