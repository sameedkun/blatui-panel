<?php

namespace App\Services\Subscription;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\PaymentProvider;
use App\Enum\SubscriptionStatus;
use App\Jobs\Subscription\SyncSubscriptionStatuses;
use App\Models\Subscription;
use App\Support\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Calendar-driven subscription status transitions — kept separate from
 * {@see SubscriptionService} (which owns user/admin-initiated state changes:
 * subscribe, upgrade, cancel, reactivate) because this one runs unattended off
 * a schedule rather than in response to an action.
 */
class LifecycleService
{
    /**
     * Advances every non-terminal subscription — across however many providers
     * are passed — whose next boundary date (trial/period/grace end) has
     * already passed. The scheduled sweep run by {@see SyncSubscriptionStatuses}.
     * Each row only moves ONE step per call (e.g. trialing -> active, never
     * straight to expired even if several boundaries have since passed); a
     * subsequent run catches anything still overdue after that step.
     *
     * Only `local` subscriptions (no real payment gateway wired in yet) can
     * have their status inferred from dates alone today — a real provider
     * (Stripe, …) must confirm the renewal charge itself via its own
     * webhook/reconciliation before it's safe to run this same transition set
     * against it. Once that exists, just add it to the provider list passed in
     * (see routes/console.php) — nothing here needs to change per-provider.
     *
     * Two transitions from the full state graph are deliberately NOT handled
     * here because they aren't calendar-driven:
     *   - active -> cancelled is always an explicit user/admin action, see
     *     {@see SubscriptionService::cancelActive()}.
     *   - grace -> active needs a real "payment received" signal this sweep has
     *     no way to produce for `local` — wire it up once a provider integration
     *     (or an admin "mark paid" action) can confirm one.
     *
     * @param  array<int, PaymentProvider>  $providers
     * @return array<string, array{trial_converted: int, entered_grace: int, expired: int}> keyed by provider value
     */
    public function syncStatuses(array $providers = [PaymentProvider::Local]): array
    {
        $results = [];

        foreach ($providers as $provider) {
            $results[$provider->value] = $this->syncProviderStatuses($provider);
        }

        return $results;
    }

    /** @return array{trial_converted: int, entered_grace: int, expired: int} */
    protected function syncProviderStatuses(PaymentProvider $provider): array
    {
        $counts = ['trial_converted' => 0, 'entered_grace' => 0, 'expired' => 0];

        // Each category below is ONE bulk UPDATE per chunk covering however many
        // rows match, not an UPDATE per subscription — the row-by-row work that's
        // left is only the (unavoidable) one audit-log INSERT per affected
        // subscription, since ActivityLogger entries carry subject-specific
        // properties that can't be bulked. Order matters between the first three:
        // entering grace must run before the active -> expired sweep, since it
        // re-scopes on status = active and must not still see rows the grace step
        // just moved off that status. The trial_converted and trial-lapsed steps
        // never overlap — they partition trialing rows on is_recurring (true vs
        // false) — so their relative order is not load-bearing.

        $counts['trial_converted'] = $this->bulkFlip(
            $provider,
            fn (Builder $q) => $q->where('status', SubscriptionStatus::Trialing)
                ->where('trial_ends_at', '<=', now())
                ->where('is_recurring', true),
            ['status' => SubscriptionStatus::Active],
            'subscription_trial_converted',
        );

        // trialing -> expired (no renewal set up). ends_at was already set to the
        // trial boundary at creation (see SubscriptionService::computeDates), so
        // there's nothing to rewrite — a plain status flip like the others.
        $counts['expired'] += $this->bulkFlip(
            $provider,
            fn (Builder $q) => $q->where('status', SubscriptionStatus::Trialing)
                ->where('trial_ends_at', '<=', now())
                ->where('is_recurring', false),
            ['status' => SubscriptionStatus::Expired],
            'subscription_expired',
            'trial_lapsed',
        );

        $counts['entered_grace'] = $this->bulkFlip(
            $provider,
            fn (Builder $q) => $q->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '<=', now())
                ->where('is_recurring', true)
                ->whereNotNull('grace_ends_at')
                ->where('grace_ends_at', '>', now()),
            ['status' => SubscriptionStatus::Grace],
            'subscription_entered_grace',
        );

        $counts['expired'] += $this->bulkFlip(
            $provider,
            fn (Builder $q) => $q->where('status', SubscriptionStatus::Active)
                ->where('ends_at', '<=', now())
                ->where(fn (Builder $q2) => $q2->where('is_recurring', false)
                    ->orWhereNull('grace_ends_at')
                    ->orWhere('grace_ends_at', '<=', now())),
            ['status' => SubscriptionStatus::Expired],
            'subscription_expired',
            fn (Subscription $s): string => $s->is_recurring ? 'renewal_unconfirmed' : 'not_recurring',
        );

        $counts['expired'] += $this->bulkFlip(
            $provider,
            fn (Builder $q) => $q->where('status', SubscriptionStatus::Grace)
                ->where('grace_ends_at', '<=', now()),
            ['status' => SubscriptionStatus::Expired],
            'subscription_expired',
            'grace_exhausted',
        );

        $counts['expired'] += $this->bulkFlip(
            $provider,
            fn (Builder $q) => $q->where('status', SubscriptionStatus::Cancelled)
                ->where('ends_at', '<=', now()),
            ['status' => SubscriptionStatus::Expired],
            'subscription_expired',
            'cancellation_ended',
        );

        return $counts;
    }

    /**
     * Runs one bulk UPDATE per chunk of subscriptions matching `$scope`, then
     * logs each affected row individually (subject-specific audit properties
     * can't be bulked) — collapses N row-by-row UPDATE queries into a single
     * statement per chunk, while hydration stays bounded to chunk size so a
     * large sweep never loads every matching model at once.
     *
     * @param  Closure(Builder<Subscription>): (Builder<Subscription>|void)  $scope
     * @param  array<string, mixed>  $attributes
     * @param  (Closure(Subscription): ?string)|string|null  $reason
     */
    protected function bulkFlip(PaymentProvider $provider, Closure $scope, array $attributes, string $type, Closure|string|null $reason = null): int
    {
        $query = Subscription::query()->where('provider', $provider);
        $scope($query);

        // Snapshot matching IDs before any write: the bulk UPDATE mutates the
        // very `status` column the scope filters on, so a live cursor would
        // shift underneath itself and skip rows.
        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        foreach ($ids->chunk(500) as $chunk) {
            $subscriptions = Subscription::query()
                ->whereIn('id', $chunk)
                ->with(['user', 'plan'])
                ->get();

            Subscription::query()->whereIn('id', $chunk)->update($attributes);

            foreach ($subscriptions as $subscription) {
                // Resolve the reason against pre-update state — the closure
                // inspects the model, so it must run before forceFill().
                $resolvedReason = $reason instanceof Closure ? $reason($subscription) : $reason;
                $subscription->forceFill($attributes);
                $this->logTransition($subscription, $type, $resolvedReason);
            }
        }

        return $ids->count();
    }

    /**
     * The sweep runs with no auth() session, so log with an explicit null
     * (system) causer and a Scheduler context rather than trusting the ambient
     * runtime — mirrors DeletionService::purge()'s scheduled path.
     */
    protected function logTransition(Subscription $subscription, string $type, ?string $reason = null): void
    {
        if (! $subscription->user) {
            return;
        }

        ActivityLogger::log(ActivityModule::User, ActivityAction::Updated, $subscription->user, [
            'type' => $type,
            'plan' => $subscription->plan?->name,
            'reason' => $reason,
        ], causer: null, context: ActivityContext::Scheduler);
    }
}
