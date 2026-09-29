<?php

namespace App\Support\Dashboard\Metrics;

use App\Enum\ActivityAction;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use App\Enum\UserType;
use App\Models\User;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\TimeSeries;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Who uses the product and how that is changing.
 *
 * "Users" means app users (`type = app`) throughout — staff and guests are
 * reported separately. "Active" means signed in during the window, read from
 * the authentication audit trail rather than `users.last_login`: that column
 * only holds the latest login, so it can't answer "was this user active last
 * month?" once they have logged in again since. Guests are never written to
 * the auth log (see AuthActivityListener), so they never count as active.
 */
class AudienceMetrics
{
    public function totalUsers(?CarbonInterface $asOf = null): int
    {
        return User::query()->appUsers()
            ->when($asOf, fn (Builder $query) => $query->where('created_at', '<=', $asOf))
            ->count();
    }

    public function newUsers(DateRange $range): int
    {
        return User::query()->appUsers()->whereBetween('created_at', [$range->start, $range->end])->count();
    }

    public function guests(?CarbonInterface $asOf = null): int
    {
        return User::query()->guests()
            ->when($asOf, fn (Builder $query) => $query->where('created_at', '<=', $asOf))
            ->count();
    }

    public function newGuests(DateRange $range): int
    {
        return User::query()->guests()->whereBetween('created_at', [$range->start, $range->end])->count();
    }

    /** App users who signed in at least once during the window. */
    public function activeUsers(DateRange $range): int
    {
        return (int) $this->logins()
            ->whereBetween('created_at', [$range->start, $range->end])
            ->distinct()
            ->count('causer_id');
    }

    /** Active users whose account already existed before the window began. */
    public function returningUsers(DateRange $range): int
    {
        return (int) $this->logins()
            ->whereBetween('created_at', [$range->start, $range->end])
            ->whereIn('causer_id', User::query()->appUsers()->where('created_at', '<', $range->start)->select('id'))
            ->distinct()
            ->count('causer_id');
    }

    /** Guests converted into app accounts during the window (self-service or admin). */
    public function guestConversions(DateRange $range): int
    {
        return Activity::query()
            ->where('event', ActivityAction::Converted->value)
            ->where('properties->module', ActivityModule::Guest->value)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->count();
    }

    /**
     * Conversions as a share of every guest the window could have converted:
     * those converted plus the guests created in the window that are still
     * guests. (A converted guest is no longer `type = guest`, so "new guests"
     * alone would silently shrink the denominator by exactly the successes.)
     */
    public function guestConversionRate(DateRange $range): float
    {
        $conversions = $this->guestConversions($range);

        return Format::share($conversions, $conversions + $this->newGuests($range));
    }

    /** Share of the window's new users who verified their email. */
    public function activationRate(DateRange $range): float
    {
        $new = $this->newUsers($range);
        $verified = User::query()->appUsers()
            ->whereBetween('created_at', [$range->start, $range->end])
            ->whereNotNull('email_verified_at')
            ->count();

        return Format::share($verified, $new);
    }

    /**
     * Of the users who signed up in the previous window, the share that
     * signed in again during this one.
     */
    public function retentionRate(DateRange $range): float
    {
        $previous = $range->previous();
        $cohort = User::query()->appUsers()->whereBetween('created_at', [$previous->start, $previous->end]);
        $cohortSize = (clone $cohort)->count();

        if ($cohortSize === 0) {
            return 0.0;
        }

        $retained = (int) $this->logins()
            ->whereBetween('created_at', [$range->start, $range->end])
            ->whereIn('causer_id', $cohort->select('id'))
            ->distinct()
            ->count('causer_id');

        return Format::share($retained, $cohortSize);
    }

    /** Average new users per day across the window. */
    public function registrationRate(DateRange $range): float
    {
        return round($this->newUsers($range) / max(1, $range->days()), 1);
    }

    /** @return array<string, int> new app users per bucket */
    public function registrationSeries(DateRange $range): array
    {
        return TimeSeries::count(User::query()->appUsers(), $range);
    }

    /** @return array<string, int> running total of app users per bucket */
    public function growthSeries(DateRange $range): array
    {
        return TimeSeries::cumulative(
            $this->registrationSeries($range),
            User::query()->appUsers()->where('created_at', '<', $range->start)->count(),
        );
    }

    /** @return array<string, int> distinct signed-in app users per bucket */
    public function activeSeries(DateRange $range): array
    {
        return TimeSeries::countDistinct($this->logins(), $range, 'causer_id');
    }

    /** @return array<string, int> distinct signed-in users per bucket whose account predates the window */
    public function returningSeries(DateRange $range): array
    {
        return TimeSeries::countDistinct(
            $this->logins()->whereIn('causer_id', User::query()->appUsers()->where('created_at', '<', $range->start)->select('id')),
            $range,
            'causer_id',
        );
    }

    /** @return array<string, int> guests created per bucket */
    public function guestSeries(DateRange $range): array
    {
        return TimeSeries::count(User::query()->guests(), $range);
    }

    /**
     * Every account type plus the account states worth watching.
     *
     * @return array{app: int, guests: int, staff: int, banned: int, pending_deletion: int, unverified: int}
     */
    public function breakdown(): array
    {
        return [
            'app' => $this->totalUsers(),
            'guests' => $this->guests(),
            'staff' => User::query()->staff()->count(),
            'banned' => User::query()->where('type', '!=', UserType::Staff->value)->banned()->count(),
            'pending_deletion' => User::query()->appUsers()->pendingDeletion()->count(),
            'unverified' => User::query()->appUsers()->whereNull('email_verified_at')->count(),
        ];
    }

    /**
     * How the window's new users signed up.
     *
     * @return array{email: int, google: int, apple: int}
     */
    public function signupMethods(DateRange $range): array
    {
        $base = fn (): Builder => User::query()->appUsers()->whereBetween('created_at', [$range->start, $range->end]);

        $google = $base()->whereNotNull('google_id')->count();
        $apple = $base()->whereNull('google_id')->whereNotNull('apple_id')->count();

        return [
            'email' => $base()->count() - $google - $apple,
            'google' => $google,
            'apple' => $apple,
        ];
    }

    /** Successful app-user sign-ins, as recorded by AuthActivityListener. */
    private function logins(): Builder
    {
        return Activity::query()
            ->where('log_name', ActivityLogName::Authentication->value)
            ->where('event', ActivityAction::Login->value)
            ->where('properties->module', ActivityModule::User->value)
            ->where('causer_type', (new User)->getMorphClass());
    }
}
