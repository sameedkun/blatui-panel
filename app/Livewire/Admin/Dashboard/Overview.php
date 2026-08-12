<?php

namespace App\Livewire\Admin\Dashboard;

use App\Support\Dashboard\DashboardMetrics;
use App\Support\Dashboard\DateRange;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * At-a-glance tab: headline numbers, the signup trend, platform health and what just
 * happened. Everything here answers "is anything wrong right now".
 */
#[Lazy]
class Overview extends Component
{
    public string $selectedRange;

    public function render(DashboardMetrics $metrics): View
    {
        $range = DateRange::fromValue($this->selectedRange);

        return view('livewire.admin.dashboard.overview', [
            'cards' => $metrics->remember('overview.kpis', $range, fn (): array => $this->kpiCards($metrics, $range)),
            'signups' => $metrics->remember('overview.signups', $range, fn (): array => $metrics->audience->signupSeries($range)),
            'split' => $metrics->remember('overview.split', $range, fn (): array => $metrics->audience->typeBreakdown()),
            // Health and the activity feed are never cached: a stale queue depth or a
            // missing "just happened" entry defeats the point of showing them.
            'health' => [
                'queued' => $metrics->system->queuedJobs(),
                'reserved' => $metrics->system->reservedJobs(),
                'failed' => $metrics->system->failedJobs(),
                'recentFailures' => $metrics->system->recentFailures(),
                'lastScheduledRun' => $metrics->system->lastScheduledRunAt(),
            ],
            'activity' => $metrics->system->recentActivity(6),
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.admin.dashboard.placeholder');
    }

    /**
     * The headline metric row.
     *
     * Each card is permission-gated individually, so the row silently narrows rather than
     * showing a viewer a figure they aren't cleared for.
     *
     * @return array<int, array<string, mixed>>
     */
    private function kpiCards(DashboardMetrics $metrics, DateRange $range): array
    {
        $user = auth()->user();

        // Each period metric returns its own value *and* its change together, so resolve
        // it once and read both off the result. Deriving them in separate closures would
        // run the same pair of count queries twice per card.
        $resolvers = [
            'users.view' => fn (): array => [
                [
                    'label' => __('dashboard.stats.total_users'),
                    'icon' => 'users',
                    'value' => number_format($metrics->audience->totalUsers()),
                    'trend' => ($signups = $metrics->audience->newUsers($range))['change'],
                    'description' => __('dashboard.stats.total_users_description'),
                ],
                [
                    'label' => __('dashboard.stats.new_signups'),
                    'icon' => 'user-plus',
                    'value' => number_format($signups['value']),
                    'trend' => $signups['change'],
                    'description' => $range->label(),
                ],
            ],
            'guests.view' => function () use ($metrics, $range): array {
                $conversions = $metrics->audience->conversions($range);

                return [[
                    'label' => __('dashboard.stats.conversions'),
                    'icon' => 'user-check',
                    'value' => number_format($conversions['value']),
                    'trend' => $conversions['change'],
                    'description' => __('dashboard.stats.conversions_description'),
                ]];
            },
            'subscriptions.view' => function () use ($metrics, $range): array {
                $revenue = $metrics->revenue->revenue($range);

                return [
                    [
                        'label' => __('dashboard.stats.active_subscriptions'),
                        'icon' => 'credit-card',
                        'value' => number_format($metrics->revenue->activeSubscriptions()),
                        'trend' => null,
                        'description' => __('dashboard.stats.active_subscriptions_description'),
                    ],
                    [
                        'label' => __('dashboard.stats.revenue'),
                        'icon' => 'banknote',
                        'value' => '$'.number_format($revenue['value'], 2),
                        'trend' => $revenue['change'],
                        'description' => $range->label(),
                    ],
                ];
            },
            'tickets.view' => fn (): array => [[
                'label' => __('dashboard.stats.open_tickets'),
                'icon' => 'life-buoy',
                'value' => number_format($metrics->support->openTickets()),
                // More tickets is worse, so the usual "up is good" colouring is inverted.
                'trend' => $metrics->support->newTickets($range)['change'],
                'invert_trend' => true,
                'description' => __('dashboard.stats.open_tickets_description'),
            ]],
            'devices.view' => fn (): array => [[
                'label' => __('dashboard.stats.active_devices'),
                'icon' => 'smartphone',
                'value' => number_format($metrics->security->activeDevices()),
                'trend' => $metrics->security->newDevices($range)['change'],
                'description' => __('dashboard.stats.active_devices_description'),
            ]],
            'blocked-ips.view' => fn (): array => [[
                'label' => __('dashboard.stats.blocked_ips'),
                'icon' => 'shield-ban',
                'value' => number_format($metrics->security->activeBlocks()),
                'trend' => null,
                'invert_trend' => true,
                'description' => __('dashboard.stats.blocked_ips_description'),
            ]],
        ];

        $cards = [];

        // Gate before resolving, so a viewer never pays for a query behind a permission
        // they don't hold.
        foreach ($resolvers as $permission => $resolve) {
            if ($user->can($permission)) {
                $cards = [...$cards, ...$resolve()];
            }
        }

        return $cards;
    }
}
