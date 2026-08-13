@php
    use App\Enum\SubscriptionStatus;

    // x-ui.chart's base classes include `aspect-video` — overridden with an explicit height.
    $chartClass = 'aspect-auto h-[240px]';
    $liveStatuses = [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Grace];
    $canManageSubs = auth()->user()->can('subscriptions.manage');
    $canViewTickets = auth()->user()->can('tickets.view');
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Plan Distribution --}}
    @if ($plans)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-layers class="size-4 text-primary" />
                        {{ __('dashboard.widgets.plan_distribution') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Active subscribers per pricing tier</x-ui.card-description>
                </div>
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

    {{-- Subscription Status Matrix --}}
    @if ($statuses)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-circle-dot class="size-4 text-emerald-500" />
                        {{ __('dashboard.widgets.subscription_status') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Lifecycle breakdown across all records</x-ui.card-description>
                </div>
                <x-ui.badge variant="outline" class="font-mono text-xs tabular-nums">
                    Total: {{ number_format(array_sum($statuses['values'])) }}
                </x-ui.badge>
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

    {{-- Trial Conversion & Unit Economics --}}
    @if ($conversion)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-target class="size-4 text-purple-500" />
                        {{ __('dashboard.widgets.trial_conversion') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Trial-to-paid conversion & ARPU</x-ui.card-description>
                </div>
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

                <div class="flex flex-col gap-2.5 border-t border-border/60 pt-3.5 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">{{ __('dashboard.labels.arpu') }}</span>
                        <span class="font-mono font-semibold text-foreground tabular-nums">${{ number_format($conversion['arpu'], 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-muted-foreground">{{ __('dashboard.labels.lifetime_revenue') }}</span>
                        <span class="font-mono font-semibold text-foreground tabular-nums">${{ number_format($conversion['lifetime'], 2) }}</span>
                    </div>
                </div>
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Ticket Priority Matrix --}}
    @if ($priorities)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-flag class="size-4 text-amber-500" />
                        {{ __('dashboard.widgets.ticket_priority') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Open tickets distribution by priority</x-ui.card-description>
                </div>
                <x-ui.badge variant="outline" class="font-mono text-xs tabular-nums">
                    Total: {{ number_format(array_sum($priorities['values'])) }}
                </x-ui.badge>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($priorities['values']) > 0)
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

    {{-- Support Agent Workload --}}
    @if ($workload)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-users class="size-4 text-blue-500" />
                        {{ __('dashboard.widgets.agent_workload') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Ticket distribution across staff agents</x-ui.card-description>
                </div>
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-4 pt-0">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                        <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.unassigned') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-foreground">{{ number_format($workload['unassigned']) }}</p>
                    </div>
                    <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                        <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.median_response') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-foreground">
                            {{ $workload['medianResponse'] !== null ? __('dashboard.labels.hours', ['value' => $workload['medianResponse']]) : '—' }}
                        </p>
                    </div>
                </div>

                @if (count($workload['agents']))
                    <div class="flex flex-col gap-3.5">
                        @foreach ($workload['agents'] as $agent)
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="truncate font-semibold text-foreground">{{ $agent['name'] }}</span>
                                    <span class="shrink-0 font-mono font-medium tabular-nums text-muted-foreground">{{ number_format($agent['total']) }}</span>
                                </div>
                                <x-ui.progress :value="$agent['share']" class="h-2" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="py-6 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Security & Device Risk --}}
    @if ($risk)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-triangle-alert class="size-4 text-amber-500" />
                        {{ __('dashboard.widgets.device_risk') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Security risk signals & shared device fingerprints</x-ui.card-description>
                </div>
                @if (auth()->user()->can('devices.view') && auth()->user()->can('devices.investigate'))
                    <a href="{{ route('admin.devices.shared-fingerprints') }}" wire:navigate
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                        <span>{{ __('dashboard.view_all') }}</span>
                        <x-lucide-arrow-right class="size-3" />
                    </a>
                @endif
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-3 pt-0">
                @foreach ([
                    ['label' => __('dashboard.labels.shared_fingerprints'), 'value' => $risk['shared'], 'icon' => 'fingerprint', 'warn' => $risk['shared'] > 0],
                    ['label' => __('dashboard.labels.blocked_devices'), 'value' => $risk['blocked'], 'icon' => 'ban', 'warn' => false],
                    ['label' => __('dashboard.labels.revoked_devices'), 'value' => $risk['revoked'], 'icon' => 'log-out', 'warn' => false],
                ] as $signal)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-border/80 bg-muted/10 p-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                <x-dynamic-component :component="'lucide-' . $signal['icon']" class="size-4" />
                            </div>
                            <span class="text-xs font-medium text-foreground">{{ $signal['label'] }}</span>
                        </div>
                        <span @class([
                            'font-mono text-lg font-bold tabular-nums',
                            'text-amber-600 dark:text-amber-400' => $signal['warn'],
                            'text-foreground' => ! $signal['warn'],
                        ])>
                            {{ number_format($signal['value']) }}
                        </span>
                    </div>
                @endforeach
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Blocked IPs --}}
    @if ($blocks)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-shield-ban class="size-4 text-rose-500" />
                        {{ __('dashboard.widgets.blocked_ips') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">IP addresses currently blocked from API & Panel access</x-ui.card-description>
                </div>
                @can('blocked-ips.view')
                    <a href="{{ route('admin.blocked-ips.index') }}" wire:navigate
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                        <span>{{ __('dashboard.view_all') }}</span>
                        <x-lucide-arrow-right class="size-3" />
                    </a>
                @endcan
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-4 pt-0">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                        <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.active_blocks') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-foreground">{{ number_format($blocks['active']) }}</p>
                    </div>
                    <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                        <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.global_blocks') }}</p>
                        <p @class(['mt-1 text-xl font-bold tabular-nums', 'text-amber-600 dark:text-amber-400' => $blocks['global'] > 0, 'text-foreground' => $blocks['global'] === 0])>
                            {{ number_format($blocks['global']) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                        <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.total_hits') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-foreground">{{ number_format($blocks['hits']) }}</p>
                    </div>
                </div>

                @if ($blocks['rows']->isNotEmpty())
                    <div class="flex flex-col divide-y divide-border/50">
                        @foreach ($blocks['rows'] as $row)
                            <div class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                <div class="flex min-w-0 flex-col">
                                    <span class="truncate font-mono text-xs font-semibold text-foreground">{{ $row->ip_address }}</span>
                                    <span class="truncate text-[11px] text-muted-foreground">
                                        {{ $row->user?->email ?? __('dashboard.labels.global') }}
                                    </span>
                                </div>
                                <span class="shrink-0 font-mono text-xs tabular-nums text-muted-foreground bg-muted/50 px-2 py-0.5 rounded-full">
                                    {{ number_format($row->hits) }} {{ __('dashboard.labels.hits') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Oldest Pending Tickets Table --}}
    @if ($oldestTickets !== null)
        <x-ui.card class="lg:col-span-2">
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-clock class="size-4 text-amber-500" />
                        {{ __('dashboard.widgets.oldest_tickets') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Support requests waiting longest for staff response</x-ui.card-description>
                </div>
                @can('tickets.view')
                    <a href="{{ route('admin.tickets.index') }}" wire:navigate
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                        <span>{{ __('dashboard.view_all') }}</span>
                        <x-lucide-arrow-right class="size-3" />
                    </a>
                @endcan
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if ($oldestTickets->isNotEmpty())
                    <div class="flex flex-col divide-y divide-border/50">
                        @foreach ($oldestTickets as $row)
                            <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="flex min-w-0 flex-col gap-1">
                                    @if ($canViewTickets)
                                        <a href="{{ route('admin.tickets.show', $row) }}" wire:navigate
                                            class="truncate text-xs font-semibold text-foreground hover:text-primary hover:underline">{{ $row->subject }}</a>
                                    @else
                                        <span class="truncate text-xs font-semibold text-foreground">{{ $row->subject }}</span>
                                    @endif
                                    <span class="truncate text-[11px] text-muted-foreground">
                                        {{ $row->user?->name ?? '—' }} &middot;
                                        Assigned to: <span class="font-medium text-foreground">{{ $row->agent?->name ?? __('dashboard.labels.unassigned_agent') }}</span>
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center gap-2.5">
                                    <x-admin.ticket-priority-badge :priority="$row->priority" />
                                    <span class="hidden font-mono text-[11px] tabular-nums text-muted-foreground sm:inline bg-muted/50 px-2 py-0.5 rounded-full">
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

    {{-- Recent Subscriptions Table --}}
    @if ($subscriptions !== null)
        <x-ui.card class="lg:col-span-2">
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-receipt class="size-4 text-emerald-500" />
                        {{ __('dashboard.widgets.recent_subscriptions') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Latest subscription sales and plan assignments</x-ui.card-description>
                </div>
                @can('subscriptions.view')
                    <a href="{{ route('admin.subscriptions.index') }}" wire:navigate
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                        <span>{{ __('dashboard.view_all') }}</span>
                        <x-lucide-arrow-right class="size-3" />
                    </a>
                @endcan
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if ($subscriptions->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-border/70 text-left font-medium text-muted-foreground uppercase tracking-wider">
                                    <th class="py-2.5 pr-4">{{ __('dashboard.labels.user') }}</th>
                                    <th class="py-2.5 pr-4">{{ __('dashboard.labels.plan') }}</th>
                                    <th class="py-2.5 pr-4">{{ __('dashboard.labels.status') }}</th>
                                    <th class="hidden py-2.5 pr-4 sm:table-cell">{{ __('dashboard.labels.started') }}</th>
                                    <th class="py-2.5 text-right">{{ __('dashboard.labels.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/50">
                                @foreach ($subscriptions as $row)
                                    <tr>
                                        <td class="py-3 pr-4 font-semibold text-foreground">
                                            <div class="flex items-center gap-2">
                                                <x-ui.avatar class="size-6">
                                                    <x-ui.avatar-fallback class="text-[10px]">{{ substr($row->user?->name ?? 'U', 0, 1) }}</x-ui.avatar-fallback>
                                                </x-ui.avatar>
                                                <span>{{ $row->user?->name ?? '—' }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 pr-4 text-muted-foreground font-medium">{{ $row->plan?->name ?? '—' }}</td>
                                        <td class="py-3 pr-4">
                                            @if (in_array($row->status, $liveStatuses, true))
                                                <x-ui.badge variant="default"
                                                    class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-medium">
                                                    {{ $row->status->label() }}
                                                </x-ui.badge>
                                            @elseif ($row->status === SubscriptionStatus::Cancelled || $row->status === SubscriptionStatus::Failed)
                                                <x-ui.badge variant="destructive">{{ $row->status->label() }}</x-ui.badge>
                                            @else
                                                <x-ui.badge variant="secondary">{{ $row->status->label() }}</x-ui.badge>
                                            @endif
                                        </td>
                                        <td class="hidden py-3 pr-4 text-muted-foreground sm:table-cell font-mono">
                                            {{ $row->starts_at?->format('M j, Y') ?? '—' }}
                                        </td>
                                        <td class="py-3 text-right font-mono font-semibold tabular-nums text-foreground">
                                            @if ($canManageSubs)
                                                <a href="{{ route('admin.subscriptions.show', $row) }}" wire:navigate
                                                    class="hover:text-primary hover:underline">${{ number_format((float) $row->amount_paid, 2) }}</a>
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
