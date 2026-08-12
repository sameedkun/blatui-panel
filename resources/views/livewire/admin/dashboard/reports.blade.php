@php
    use App\Enum\SubscriptionStatus;

    // x-ui.chart's base classes include `aspect-video` — overridden with an explicit height.
    $chartClass = 'aspect-auto h-[240px]';
    $liveStatuses = [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Grace];
    $canManageSubs = auth()->user()->can('subscriptions.manage');
    $canViewTickets = auth()->user()->can('tickets.view');
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

    @if ($plans)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-layers class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.plan_distribution') }}
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($plans['values']) > 0)
                    <x-ui.chart type="bar" height="240" :class="$chartClass"
                        :label="__('dashboard.widgets.plan_distribution')"
                        :series="[['name' => __('dashboard.series.subscribers'), 'data' => $plans['values']]]"
                        :colors="['var(--chart-1)']"
                        :options="[
                            'chart' => ['toolbar' => ['show' => false]],
                            'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4, 'barHeight' => '60%']],
                            'dataLabels' => ['enabled' => true],
                            'xaxis' => ['categories' => $plans['labels']],
                            'legend' => ['show' => false],
                            'grid' => ['strokeDashArray' => 4],
                        ]" />
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($statuses)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-circle-dot class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.subscription_status') }}
                </x-ui.card-title>
                <span class="text-xs tabular-nums text-muted-foreground">{{ number_format(array_sum($statuses['values'])) }}</span>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($statuses['values']) > 0)
                    <x-ui.chart type="bar" height="240" :class="$chartClass"
                        :label="__('dashboard.widgets.subscription_status')"
                        :series="[['name' => __('dashboard.series.subscribers'), 'data' => $statuses['values']]]"
                        :colors="['var(--chart-3)']"
                        :options="[
                            'chart' => ['toolbar' => ['show' => false]],
                            'plotOptions' => ['bar' => ['borderRadius' => 3, 'columnWidth' => '55%', 'distributed' => true]],
                            'dataLabels' => ['enabled' => false],
                            'xaxis' => ['categories' => $statuses['labels']],
                            'legend' => ['show' => false],
                            'grid' => ['strokeDashArray' => 4],
                        ]" />
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($conversion)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-target class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.trial_conversion') }}
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-4 pt-0">
                <x-ui.chart type="radialBar" height="180" class="aspect-auto h-[180px]"
                    :label="__('dashboard.labels.trial_conversion')"
                    :labels="[__('dashboard.labels.trial_conversion')]" :series="[$conversion['rate']]"
                    :colors="['var(--chart-1)']"
                    :options="[
                        'plotOptions' => ['radialBar' => [
                            'hollow' => ['size' => '62%'],
                            'dataLabels' => [
                                'name' => ['show' => false],
                                'value' => ['fontSize' => '22px', 'fontWeight' => 600, 'offsetY' => 8],
                            ],
                        ]],
                    ]" />

                <div class="flex flex-col gap-2 border-t border-border pt-3 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">{{ __('dashboard.labels.arpu') }}</span>
                        <span class="font-medium tabular-nums">${{ number_format($conversion['arpu'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">{{ __('dashboard.labels.lifetime_revenue') }}</span>
                        <span class="font-medium tabular-nums">${{ number_format($conversion['lifetime'], 2) }}</span>
                    </div>
                </div>
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($priorities)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-flag class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.ticket_priority') }}
                </x-ui.card-title>
                <span class="text-xs tabular-nums text-muted-foreground">{{ number_format(array_sum($priorities['values'])) }}</span>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($priorities['values']) > 0)
                    {{-- Colours run Low → Urgent, deliberately ordered cool to hot. --}}
                    <x-ui.chart type="donut" height="240" :class="$chartClass"
                        :label="__('dashboard.widgets.ticket_priority')"
                        :labels="$priorities['labels']" :series="$priorities['values']"
                        :colors="['var(--chart-3)', 'var(--chart-2)', 'var(--chart-4)', 'var(--chart-5)']"
                        :options="[
                            'legend' => ['show' => true, 'position' => 'bottom'],
                            'dataLabels' => ['enabled' => false],
                            'stroke' => ['width' => 0],
                            'plotOptions' => ['pie' => ['donut' => ['size' => '68%']]],
                        ]" />
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($workload)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-users class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.agent_workload') }}
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-4 pt-0">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-md border border-border p-3">
                        <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.unassigned') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($workload['unassigned']) }}</p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.median_response') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums">
                            {{ $workload['medianResponse'] !== null ? __('dashboard.labels.hours', ['value' => $workload['medianResponse']]) : '—' }}
                        </p>
                    </div>
                </div>

                @if (count($workload['agents']))
                    <div class="flex flex-col gap-3">
                        @foreach ($workload['agents'] as $agent)
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="truncate font-medium">{{ $agent['name'] }}</span>
                                    <span class="shrink-0 tabular-nums text-muted-foreground">{{ number_format($agent['total']) }}</span>
                                </div>
                                <x-ui.progress :value="$agent['share']" class="h-1.5" />
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- A category can have agents attached who have since lost ticket
                         permissions; they are excluded rather than shown at zero. --}}
                    <p class="py-6 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($risk)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-triangle-alert class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.device_risk') }}
                </x-ui.card-title>
                {{-- That route sits inside the devices group, so it needs devices.view on top
                     of devices.investigate — both are checked, not just the card's own gate. --}}
                @if (auth()->user()->can('devices.view') && auth()->user()->can('devices.investigate'))
                    <a href="{{ route('admin.devices.shared-fingerprints') }}" wire:navigate
                        class="text-xs text-muted-foreground hover:text-foreground">{{ __('dashboard.view_all') }}</a>
                @endif
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-3 pt-0">
                @foreach ([
                    ['label' => __('dashboard.labels.shared_fingerprints'), 'value' => $risk['shared'], 'icon' => 'fingerprint', 'warn' => $risk['shared'] > 0],
                    ['label' => __('dashboard.labels.blocked_devices'), 'value' => $risk['blocked'], 'icon' => 'ban', 'warn' => false],
                    ['label' => __('dashboard.labels.revoked_devices'), 'value' => $risk['revoked'], 'icon' => 'log-out', 'warn' => false],
                ] as $signal)
                    <div class="flex items-center justify-between gap-3 rounded-md border border-border p-3">
                        <div class="flex items-center gap-2">
                            <x-dynamic-component :component="'lucide-' . $signal['icon']" class="size-4 text-muted-foreground" />
                            <span class="text-xs">{{ $signal['label'] }}</span>
                        </div>
                        <span @class(['text-lg font-bold tabular-nums', 'text-amber-600 dark:text-amber-400' => $signal['warn']])>
                            {{ number_format($signal['value']) }}
                        </span>
                    </div>
                @endforeach
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($blocks)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-shield-ban class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.blocked_ips') }}
                </x-ui.card-title>
                @can('blocked-ips.view')
                    <a href="{{ route('admin.blocked-ips.index') }}" wire:navigate
                        class="text-xs text-muted-foreground hover:text-foreground">{{ __('dashboard.view_all') }}</a>
                @endcan
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-4 pt-0">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-md border border-border p-3">
                        <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.active_blocks') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($blocks['active']) }}</p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.global_blocks') }}</p>
                        {{-- A global block can lock out everyone behind one carrier NAT. --}}
                        <p @class(['mt-1 text-xl font-bold tabular-nums', 'text-amber-600 dark:text-amber-400' => $blocks['global'] > 0])>
                            {{ number_format($blocks['global']) }}
                        </p>
                    </div>
                    <div class="rounded-md border border-border p-3">
                        <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.total_hits') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($blocks['hits']) }}</p>
                    </div>
                </div>

                @if ($blocks['rows']->isNotEmpty())
                    <div class="flex flex-col divide-y divide-border/50">
                        @foreach ($blocks['rows'] as $row)
                            <div class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                <div class="flex min-w-0 flex-col">
                                    <span class="truncate font-mono text-xs font-medium">{{ $row->ip_address }}</span>
                                    <span class="truncate text-xs text-muted-foreground">
                                        {{ $row->user?->email ?? __('dashboard.labels.global') }}
                                    </span>
                                </div>
                                <span class="shrink-0 text-xs tabular-nums text-muted-foreground">
                                    {{ number_format($row->hits) }} {{ __('dashboard.labels.hits') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($oldestTickets !== null)
        <x-ui.card class="lg:col-span-2">
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-clock class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.oldest_tickets') }}
                </x-ui.card-title>
                @can('tickets.view')
                    <a href="{{ route('admin.tickets.index') }}" wire:navigate
                        class="text-xs text-muted-foreground hover:text-foreground">{{ __('dashboard.view_all') }}</a>
                @endcan
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if ($oldestTickets->isNotEmpty())
                    <div class="flex flex-col divide-y divide-border/50">
                        @foreach ($oldestTickets as $row)
                            <div class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                <div class="flex min-w-0 flex-col gap-1">
                                    @if ($canViewTickets)
                                        <a href="{{ route('admin.tickets.show', $row) }}" wire:navigate
                                            class="truncate text-sm font-medium hover:underline">{{ $row->subject }}</a>
                                    @else
                                        <span class="truncate text-sm font-medium">{{ $row->subject }}</span>
                                    @endif
                                    <span class="truncate text-xs text-muted-foreground">
                                        {{ $row->user?->name ?? '—' }} &middot;
                                        {{ $row->agent?->name ?? __('dashboard.labels.unassigned_agent') }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <x-admin.ticket-priority-badge :priority="$row->priority" />
                                    <span class="hidden text-xs tabular-nums text-muted-foreground sm:inline">
                                        {{ $row->created_at->diffForHumans(null, true) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="py-8 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($subscriptions !== null)
        <x-ui.card class="lg:col-span-2">
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-receipt class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.recent_subscriptions') }}
                </x-ui.card-title>
                @can('subscriptions.view')
                    <a href="{{ route('admin.subscriptions.index') }}" wire:navigate
                        class="text-xs text-muted-foreground hover:text-foreground">{{ __('dashboard.view_all') }}</a>
                @endcan
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if ($subscriptions->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border text-xs text-muted-foreground">
                                    <th class="py-2 pr-4 text-left font-medium">{{ __('dashboard.labels.user') }}</th>
                                    <th class="py-2 pr-4 text-left font-medium">{{ __('dashboard.labels.plan') }}</th>
                                    <th class="py-2 pr-4 text-left font-medium">{{ __('dashboard.labels.status') }}</th>
                                    <th class="hidden py-2 pr-4 text-left font-medium sm:table-cell">{{ __('dashboard.labels.started') }}</th>
                                    <th class="py-2 text-right font-medium">{{ __('dashboard.labels.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subscriptions as $row)
                                    <tr class="border-b border-border/50 last:border-0">
                                        <td class="py-2.5 pr-4 font-medium">{{ $row->user?->name ?? '—' }}</td>
                                        <td class="py-2.5 pr-4 text-muted-foreground">{{ $row->plan?->name ?? '—' }}</td>
                                        <td class="py-2.5 pr-4">
                                            @if (in_array($row->status, $liveStatuses, true))
                                                <x-ui.badge variant="default"
                                                    class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                                                    {{ $row->status->label() }}
                                                </x-ui.badge>
                                            @elseif ($row->status === SubscriptionStatus::Cancelled || $row->status === SubscriptionStatus::Failed)
                                                <x-ui.badge variant="destructive">{{ $row->status->label() }}</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="secondary">{{ $row->status->label() }}</x-ui.badge>
                                            @endif
                                        </td>
                                        <td class="hidden py-2.5 pr-4 text-muted-foreground sm:table-cell">
                                            {{ $row->starts_at?->format('M j, Y') ?? '—' }}
                                        </td>
                                        <td class="py-2.5 text-right tabular-nums">
                                            @if ($canManageSubs)
                                                <a href="{{ route('admin.subscriptions.show', $row) }}" wire:navigate
                                                    class="font-medium hover:underline">${{ number_format((float) $row->amount_paid, 2) }}</a>
                                            @else
                                                ${{ number_format((float) $row->amount_paid, 2) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="py-8 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

</div>
