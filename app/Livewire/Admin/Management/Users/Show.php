<?php

namespace App\Livewire\Admin\Management\Users;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasActivityDetailModal;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Users\Concerns\HandlesUserRowActions;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

/**
 * Permanent resource page for a single user — the read-focused profile that
 * grows over time as modules ship (Subscriptions, Devices, Tickets, …). It is
 * NOT an editor: editing stays on the users.edit route.
 *
 * The page shell (header → stats → tabs → active tab) is stable. A new module
 * integrates by registering a tab or replacing a tab's renderer in {@see tabs()};
 * it never restructures the shell or the Overview. Header actions reuse the
 * shared {@see HandlesUserRowActions} — a ban here is byte-for-byte the same
 * action (and audit-log row) as a ban from the index.
 */
#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use HandlesUserRowActions;
    use HasActivityDetailModal;
    use HasShowTabs;
    use LogsAdminActivity;
    use WithPagination;

    public function mount(User $user): void
    {
        $this->initShow($user);
    }

    protected function indexRoute(): string
    {
        return 'admin.users.index';
    }

    protected function title(): string
    {
        return $this->record->name;
    }

    /**
     * The tab registry defines display order. Every tab is registered and shown;
     * unbuilt ones render a "coming soon" placeholder. Swapping a renderer later
     * (Blade partial → lazy Livewire component) touches only its `view` here.
     */
    protected function tabs(): array
    {
        return [
            'overview' => [
                'label' => 'Overview',
                'icon' => 'user',
                'view' => 'livewire.admin.management.users.profile.tabs.overview',
            ],
            'subscriptions' => $this->comingSoonTab('Subscriptions', 'credit-card', 'Subscriptions for this account will appear here once billing ships.'),
            'devices' => $this->comingSoonTab('Devices', 'smartphone', 'Devices registered to this account will appear here once device management ships.'),
            'activity' => [
                'label' => 'Activity',
                'icon' => 'activity',
                'view' => 'livewire.admin.management.users.profile.tabs.activity',
                'permission' => 'activity_logs.view',
                'data' => fn (): array => [
                    'activities' => $this->recordActivity(),
                    'selectedActivity' => $this->selectedActivityDetail(),
                ],
            ],
            'tickets' => $this->comingSoonTab('Tickets', 'ticket', 'Support tickets opened by this account will appear here once support ships.'),
        ];
    }

    /** Paginated audit trail for this record — powers the profile's Activity tab. */
    protected function recordActivity(): LengthAwarePaginator
    {
        return Activity::forSubject($this->record)
            ->with('causer')
            ->latest()
            ->paginate(10);
    }

    /**
     * Instant purge removes the record, so — unlike the index — the profile has
     * nowhere to stay and returns to the list. The deletion itself (and its audit
     * entry) still runs through {@see AccountDeletionService}; no new logic here.
     */
    public function instantPurge(AccountDeletionService $deletions)
    {
        $this->authorize('users.force-delete');

        $user = User::query()->appUsers()->withTrashed()->findOrFail($this->purgingId);
        $this->assertLifecycleState($user, ['pending']);

        $name = $user->name;
        $deletions->instantPurgeByAdmin($user, trim($this->purgeReason) ?: null);

        session()->flash('toast', ['type' => 'success', 'title' => "{$name} has been permanently deleted."]);

        return $this->redirect(route('admin.users.index'));
    }

    /**
     * Force-delete also removes the record — same "nowhere to stay" reasoning as
     * {@see instantPurge()} — so the profile redirects back to the list on success.
     */
    public function forceDelete()
    {
        $this->authorize('users.force-delete');

        $user = User::withTrashed()->findOrFail($this->forceDeleteId);
        $this->assertLifecycleState($user, ['trashed']);

        $name = $user->name;

        $this->logActivity(ActivityModule::User, ActivityAction::ForceDeleted, $user, ['user_id' => $user->id, 'name' => $name]);

        $user->forceDelete();

        session()->flash('toast', ['type' => 'success', 'title' => "{$name} has been permanently deleted."]);

        return $this->redirect(route('admin.users.index'));
    }

    /**
     * Marks the email verified directly, with no notification sent — mirrors
     * what the user clicking their own verification link would do. Audit
     * logging happens centrally in AuthActivityListener::handleVerified(),
     * triggered by the Verified event fired below.
     */
    public function verifyEmailManually(): void
    {
        $this->authorize('users.manage');

        $user = $this->record;

        if ($user->hasVerifiedEmail()) {
            $this->toastInfo('Already verified', "{$user->name}'s email is already verified.");

            return;
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        $this->toastSuccess("{$user->name}'s email has been verified.");
    }

    public function resendVerificationEmail(): void
    {
        $this->authorize('users.manage');

        $user = $this->record;

        if ($user->hasVerifiedEmail()) {
            $this->toastInfo('Already verified', "{$user->name}'s email is already verified.");

            return;
        }

        $user->sendEmailVerificationNotification();

        $this->logActivity(ActivityModule::User, ActivityAction::Sent, $user, ['type' => 'email_verification']);

        $this->toastSuccess("Verification email sent to {$user->email}.");
    }

    public function sendPasswordResetLink(): void
    {
        $this->authorize('users.manage');

        $user = $this->record;

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->toastError('Could not send reset link', __($status));

            return;
        }

        $this->logActivity(ActivityModule::User, ActivityAction::Sent, $user, ['type' => 'password_reset']);

        $this->toastSuccess("Password reset link sent to {$user->email}.");
    }

    /**
     * Summary cards under the header. Real values come from their own accessor so
     * a future module fills one in without touching the layout; unbuilt metrics
     * return null and render a "Coming soon" placeholder — never a fake "0".
     *
     * @return array<int, array{label: string, icon: string, value: string|null}>
     */
    public function statCards(): array
    {
        return [
            ['label' => 'Subscriptions', 'icon' => 'credit-card', 'value' => null],
            ['label' => 'Devices', 'icon' => 'smartphone', 'value' => null],
            ['label' => 'Tickets', 'icon' => 'ticket', 'value' => null],
            ['label' => 'Activity', 'icon' => 'activity', 'value' => $this->recordActivityCount()],
            ['label' => 'Joined', 'icon' => 'calendar', 'value' => $this->record->registration_date?->format('M d, Y') ?? '—'],
        ];
    }

    /** Total audit-log rows for this record, or null (renders "Coming soon") without permission to see them. */
    protected function recordActivityCount(): ?string
    {
        return auth()->user()->can('activity_logs.view')
            ? (string) Activity::forSubject($this->record)->count()
            : null;
    }

    public function render(): View
    {
        // Reflect any header action (ban, schedule, stop) on this same request.
        $this->refreshRecord();

        return view('livewire.admin.management.users.show', [
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
