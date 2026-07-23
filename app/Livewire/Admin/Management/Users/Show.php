<?php

namespace App\Livewire\Admin\Management\Users;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\CancelledBy;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasActivityDetailModal;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Users\Concerns\HandlesUserRowActions;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\SubscriptionService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use InvalidArgumentException;
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

    /** Plan/price picked in the "Assign / Change Plan" dialog. */
    public ?int $assignPlanId = null;

    public ?int $assignPriceId = null;

    /** Shared reason field for the two cancel dialogs (only one is open at a time). */
    public string $cancelReason = '';

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

    protected function viewPermission(): ?string
    {
        return 'users.manage';
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
            'subscriptions' => [
                'label' => 'Subscriptions',
                'icon' => 'credit-card',
                'view' => 'livewire.admin.management.users.profile.tabs.subscriptions',
                'data' => fn (): array => [
                    'subscriptionHistory' => $this->subscriptionHistory(),
                    'reactivatable' => $this->reactivatableSubscription(),
                ],
            ],
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

    // -------------------------------------------------------------------------
    // Subscription management
    // -------------------------------------------------------------------------

    /** Every subscription this account has ever had, newest first — powers the Subscriptions tab history table. */
    protected function subscriptionHistory(): LengthAwarePaginator
    {
        return $this->record->subscriptions()
            ->with(['plan', 'planPrice'])
            ->latest('starts_at')
            ->paginate(10, pageName: 'subs_page');
    }

    /** A cancelled-but-still-in-period subscription that can be undone, if any. */
    protected function reactivatableSubscription(): ?Subscription
    {
        return $this->record->subscriptions()
            ->where('status', 'cancelled')
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();
    }

    /** [plan id => name] for the active, purchasable plans offered in the assign/change dialog. */
    protected function planOptions(): array
    {
        return Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all();
    }

    /** [price id => label] for a given plan's active prices. */
    protected function priceOptionsFor(?int $planId): array
    {
        if (! $planId) {
            return [];
        }

        return PlanPrice::query()
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->orderBy('amount')
            ->get()
            ->mapWithKeys(fn (PlanPrice $price): array => [
                $price->id => "{$price->currency} {$price->amount} / {$price->billing_period} "
                    .$price->billing_interval->label().($price->billing_period > 1 ? 's' : ''),
            ])
            ->all();
    }

    public function openAssignPlanDialog(): void
    {
        $this->authorize('users.manage');

        $current = $this->record->activeSubscription;
        $this->assignPlanId = $current?->plan_id;
        $this->assignPriceId = $current?->plan_price_id;

        $this->dispatch('open-dialog-assign-plan');
    }

    /** Reset the price when the plan changes so a stale price is never submitted. */
    public function updatedAssignPlanId(): void
    {
        $options = $this->priceOptionsFor($this->assignPlanId);

        if (! isset($options[$this->assignPriceId])) {
            $this->assignPriceId = array_key_first($options);
        }
    }

    public function assignPlan(SubscriptionService $service): void
    {
        $this->authorize('users.manage');

        $this->validate([
            'assignPlanId' => ['required', 'integer'],
            'assignPriceId' => ['required', 'integer'],
        ]);

        $price = PlanPrice::findOrFail($this->assignPriceId);
        $user = $this->record;

        $subscription = $user->activeSubscription
            ? $service->upgrade($user, $price)
            : $service->subscribe($user, $price);

        $this->assignPlanId = null;
        $this->assignPriceId = null;

        $this->toastSuccess("{$user->name} is now on the {$subscription->plan->name} plan.");
    }

    public function openCancelImmediatelyDialog(): void
    {
        $this->authorize('users.manage');

        $this->cancelReason = '';
        $this->dispatch('open-dialog-cancel-immediately');
    }

    public function cancelImmediately(SubscriptionService $service): void
    {
        $this->authorize('users.manage');

        $user = $this->record;
        $active = $user->activeSubscription;

        if (! $active) {
            $this->toastError('This user has no active subscription.');

            return;
        }

        $planName = $active->plan->name;
        $service->cancelActive($user, CancelledBy::Admin, trim($this->cancelReason) ?: null, true);

        $this->cancelReason = '';
        $this->toastSuccess("{$planName} subscription cancelled immediately.");
    }

    public function openCancelAtPeriodEndDialog(): void
    {
        $this->authorize('users.manage');

        $this->cancelReason = '';
        $this->dispatch('open-dialog-cancel-at-period-end');
    }

    public function cancelAtPeriodEnd(SubscriptionService $service): void
    {
        $this->authorize('users.manage');

        $user = $this->record;
        $active = $user->activeSubscription;

        if (! $active) {
            $this->toastError('This user has no active subscription.');

            return;
        }

        $planName = $active->plan->name;
        $endsAt = $active->ends_at;
        $service->cancelActive($user, CancelledBy::Admin, trim($this->cancelReason) ?: null, false);

        $this->cancelReason = '';
        $this->toastSuccess("{$planName} subscription will end on ".$endsAt?->format('M d, Y').'.');
    }

    public function reactivateSubscription(SubscriptionService $service): void
    {
        $this->authorize('users.manage');

        try {
            $subscription = $service->reactivate($this->record);
            $this->toastSuccess("{$subscription->plan->name} subscription reactivated.");
        } catch (InvalidArgumentException $e) {
            $this->toastError($e->getMessage());
        }
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
            ['label' => 'Plan', 'icon' => 'credit-card', 'value' => $this->record->activeSubscription?->plan?->name ?? 'Free'],
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
            'planOptions' => $this->planOptions(),
            'priceOptions' => $this->priceOptionsFor($this->assignPlanId),
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
