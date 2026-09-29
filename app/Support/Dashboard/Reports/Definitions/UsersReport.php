<?php

namespace App\Support\Dashboard\Reports\Definitions;

use App\Enum\UserType;
use App\Models\User;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\AudienceMetrics;
use App\Support\Dashboard\Reports\ReportDefinition;
use App\Support\Dashboard\Reports\ReportFilter;
use Illuminate\Database\Eloquent\Builder;

/** Every account registered in the period — the user-growth dataset. */
class UsersReport extends ReportDefinition
{
    public function __construct(private readonly AudienceMetrics $audience) {}

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return __('dashboard.reports.definitions.users.label');
    }

    public function description(): string
    {
        return __('dashboard.reports.definitions.users.description');
    }

    public function icon(): string
    {
        return 'users';
    }

    public function permission(): ?string
    {
        return 'users.view';
    }

    public function filters(): array
    {
        return [
            ReportFilter::select('type', __('dashboard.reports.filters.account_type'), [
                UserType::App->value => __('dashboard.labels.app_users'),
                UserType::Guest->value => __('dashboard.labels.guests'),
            ]),
            ReportFilter::select('status', __('dashboard.reports.filters.status'), [
                'active' => __('dashboard.reports.values.active'),
                'suspended' => __('dashboard.labels.suspended'),
                'pending_deletion' => __('dashboard.labels.pending_deletion'),
                'unverified' => __('dashboard.labels.unverified'),
            ]),
        ];
    }

    public function columns(): array
    {
        return [
            'id' => __('dashboard.reports.columns.id'),
            'name' => __('dashboard.reports.columns.name'),
            'email' => __('dashboard.reports.columns.email'),
            'type' => __('dashboard.reports.columns.type'),
            'status' => __('dashboard.reports.columns.status'),
            'verified' => __('dashboard.reports.columns.verified'),
            'registered_at' => __('dashboard.reports.columns.registered_at'),
            'last_login' => __('dashboard.reports.columns.last_login'),
        ];
    }

    public function rows(DateRange $range, array $filters): iterable
    {
        foreach ($this->query($range, $filters)->lazyById(500) as $user) {
            yield [
                'id' => $user->external_id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->isGuest() ? __('dashboard.labels.guest') : __('dashboard.labels.app_user'),
                'status' => match (true) {
                    $user->isBanned() => __('dashboard.labels.suspended'),
                    $user->isPendingDeletion() => __('dashboard.labels.pending_deletion'),
                    default => __('dashboard.reports.values.active'),
                },
                'verified' => $user->email_verified_at ? __('dashboard.reports.values.yes') : __('dashboard.reports.values.no'),
                'registered_at' => $user->created_at?->format('Y-m-d H:i'),
                'last_login' => $user->last_login?->format('Y-m-d H:i'),
            ];
        }
    }

    public function summary(DateRange $range, array $filters): array
    {
        $total = $this->query($range, $filters)->count();

        return [
            __('dashboard.reports.summary.registered') => Format::value($total),
            __('dashboard.reports.summary.verified') => Format::value(Format::share($this->query($range, $filters)->whereNotNull('email_verified_at')->count(), $total), Format::PERCENT),
            __('dashboard.reports.summary.total_users') => Format::value($this->audience->totalUsers()),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return Builder<User>
     */
    private function query(DateRange $range, array $filters): Builder
    {
        return User::query()
            ->whereIn('type', [UserType::App->value, UserType::Guest->value])
            ->whereBetween('created_at', [$range->start, $range->end])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => match ($status) {
                'suspended' => $query->whereNotNull('banned_at'),
                'pending_deletion' => $query->whereNotNull('deletion_requested_at'),
                'unverified' => $query->whereNull('email_verified_at'),
                default => $query->whereNull('banned_at')->whereNull('deletion_requested_at'),
            });
    }
}
