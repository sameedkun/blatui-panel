@php
    use App\Support\ActivityPresenter;

    // x-ui.chart ships with `aspect-video` in its base classes — override with aspect-auto + explicit height.
    $chartClass = 'aspect-auto h-[260px]';
    $degraded = $health['recentFailures'] > 0;

    $cardThemes = [
        'users' => [
            'bg' => 'bg-blue-500/10 dark:bg-blue-500/15',
            'text' => 'text-blue-600 dark:text-blue-400',
            'accent' => 'border-t-blue-500',
        ],
        'user-plus' => [
            'bg' => 'bg-sky-500/10 dark:bg-sky-500/15',
            'text' => 'text-sky-600 dark:text-sky-400',
            'accent' => 'border-t-sky-500',
        ],
        'user-check' => [
            'bg' => 'bg-violet-500/10 dark:bg-violet-500/15',
            'text' => 'text-violet-600 dark:text-violet-400',
            'accent' => 'border-t-violet-500',
        ],
        'credit-card' => [
            'bg' => 'bg-indigo-500/10 dark:bg-indigo-500/15',
            'text' => 'text-indigo-600 dark:text-indigo-400',
            'accent' => 'border-t-indigo-500',
        ],
        'banknote' => [
            'bg' => 'bg-emerald-500/10 dark:bg-emerald-500/15',
            'text' => 'text-emerald-600 dark:text-emerald-400',
            'accent' => 'border-t-emerald-500',
        ],
        'life-buoy' => [
            'bg' => 'bg-amber-500/10 dark:bg-amber-500/15',
            'text' => 'text-amber-600 dark:text-amber-400',
            'accent' => 'border-t-amber-500',
        ],
        'smartphone' => [
            'bg' => 'bg-purple-500/10 dark:bg-purple-500/15',
            'text' => 'text-purple-600 dark:text-purple-400',
            'accent' => 'border-t-purple-500',
        ],
        'shield-ban' => [
            'bg' => 'bg-rose-500/10 dark:bg-rose-500/15',
            'text' => 'text-rose-600 dark:text-rose-400',
            'accent' => 'border-t-rose-500',
        ],
    ];
@endphp

<div class="flex flex-col gap-6">

    {{-- Compact, Sleek KPI Cards Grid --}}
    @if (count($cards))
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                @php
                    $hasTrend = isset($card['trend']) && $card['trend'] !== null;
                    $rising = $hasTrend && $card['trend'] > 0;
                    $flat = $hasTrend && (float) $card['trend'] === 0.0;
                    $invert = $card['invert_trend'] ?? false;
                    $good = $invert ? ! $rising : $rising;

                    $trendBadgeClass = match (true) {
                        ! $hasTrend, $flat => 'bg-muted text-muted-foreground border border-border/50',
                        $good => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
                        default => 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/20',
                    };

                    $trendIcon = match (true) {
                        $flat => 'minus',
                        $rising => 'trending-up',
                        default => 'trending-down',
                    };

                    $theme = $cardThemes[$card['icon']] ?? [
                        'bg' => 'bg-primary/10',
                        'text' => 'text-primary',
                        'accent' => 'border-t-primary',
                    ];
                @endphp
                <x-ui.card class="relative overflow-hidden border-t-2 {{ $theme['accent'] }} p-3.5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">{{ $card['label'] }}</span>
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-lg {{ $theme['bg'] }} {{ $theme['text'] }}">
                            <x-dynamic-component :component="'lucide-' . $card['icon']" class="size-3.5 shrink-0" />
                        </div>
                    </div>

                    <div class="mt-1.5 flex items-baseline justify-between gap-2">
                        <div class="text-xl font-bold font-mono tracking-tight text-foreground">
                            {{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}
                        </div>

                        @if ($hasTrend)
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $trendBadgeClass }}">
                                <x-dynamic-component :component="'lucide-' . $trendIcon" class="size-2.5" />
                                {{ $rising ? '+' : '' }}{{ $card['trend'] }}%
                            </span>
                        @endif
                    </div>

                    @if (! empty($card['description']))
                        <div class="mt-2 pt-1.5 border-t border-border/40 text-[11px] text-muted-foreground">
                            <span>{{ $card['description'] }}</span>
                        </div>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
    @endif

    {{-- Main Charts Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Signups over time --}}
        @can('users.view')
            <x-ui.card class="lg:col-span-2">
                <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                    <div class="flex flex-col gap-1">
                        <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                            <x-lucide-user-plus class="size-4 text-sky-500" />
                            {{ __('dashboard.widgets.signups') }}
                        </x-ui.card-title>
                        <x-ui.card-description class="text-xs">New registered accounts vs anonymous guests</x-ui.card-description>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.badge variant="outline" class="text-xs font-normal font-mono">
                            Total: {{ number_format(array_sum($signups['users']) + array_sum($signups['guests'])) }}
                        </x-ui.badge>
                    </div>
                </x-ui.card-header>
                <x-ui.card-content class="pt-0">
                    @if (array_sum($signups['users']) > 0 || array_sum($signups['guests']) > 0)
                        <x-ui.chart type="area" height="260" :class="$chartClass"
                            :label="__('dashboard.widgets.signups')"
                            :colors="['var(--chart-1)', 'var(--chart-2)']"
                            :series="[
                                ['name' => __('dashboard.series.users'), 'data' => $signups['users']],
                                ['name' => __('dashboard.series.guests'), 'data' => $signups['guests']],
                            ]"
                            :options="[
                                'chart' => ['stacked' => true, 'toolbar' => ['show' => false]],
                                'stroke' => ['width' => 2, 'curve' => 'smooth'],
                                'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.45, 'opacityTo' => 0.05, 'stops' => [5, 95]]],
                                'dataLabels' => ['enabled' => false],
                                'xaxis' => ['categories' => $signups['labels'], 'tickAmount' => 6],
                                'legend' => ['show' => true, 'position' => 'bottom'],
                                'grid' => ['strokeDashArray' => 4],
                            ]" />
                    @else
                        <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                    @endif
                </x-ui.card-content>
            </x-ui.card>

            {{-- Audience split --}}
            <x-ui.card>
                <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                    <div class="flex flex-col gap-1">
                        <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                            <x-lucide-pie-chart class="size-4 text-violet-500" />
                            {{ __('dashboard.widgets.audience_split') }}
                        </x-ui.card-title>
                        <x-ui.card-description class="text-xs">User composition by account type</x-ui.card-description>
                    </div>
                    <span class="text-xs font-mono font-semibold tabular-nums text-muted-foreground">
                        {{ number_format(array_sum($split['values'])) }}
                    </span>
                </x-ui.card-header>
                <x-ui.card-content class="pt-0">
                    @if (array_sum($split['values']) > 0)
                        <x-ui.chart type="donut" height="260" :class="$chartClass"
                            :label="__('dashboard.widgets.audience_split')"
                            :labels="$split['labels']" :series="$split['values']"
                            :colors="['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)']"
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
        @endcan

        {{-- Platform Health Card --}}
        @can('activity_logs.view')
            <x-ui.card class="lg:col-span-1">
                <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                    <div class="flex flex-col gap-1">
                        <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                            <x-lucide-heart-pulse class="size-4 text-rose-500" />
                            {{ __('dashboard.widgets.system_health') }}
                        </x-ui.card-title>
                        <x-ui.card-description class="text-xs">Background queue & worker telemetry</x-ui.card-description>
                    </div>
                    <x-ui.badge :variant="$degraded ? 'destructive' : 'secondary'">
                        {{ $degraded ? __('dashboard.health.degraded') : __('dashboard.health.healthy') }}
                    </x-ui.badge>
                </x-ui.card-header>
                <x-ui.card-content class="flex flex-col gap-3.5 pt-0">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                            <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.queued_jobs') }}</p>
                            <p class="mt-1 text-xl font-bold font-mono tabular-nums text-foreground">{{ number_format($health['queued']) }}</p>
                        </div>
                        <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                            <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.reserved_jobs') }}</p>
                            <p class="mt-1 text-xl font-bold font-mono tabular-nums text-foreground">{{ number_format($health['reserved']) }}</p>
                        </div>
                        <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                            <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.failed_jobs') }}</p>
                            <p class="mt-1 text-xl font-bold font-mono tabular-nums text-foreground">{{ number_format($health['failed']) }}</p>
                        </div>
                        <div class="rounded-xl border border-border/80 bg-muted/20 p-3">
                            <p class="text-[11px] font-medium text-muted-foreground">{{ __('dashboard.labels.recent_failures') }}</p>
                            <p @class(['mt-1 text-xl font-bold font-mono tabular-nums', 'text-rose-600 dark:text-rose-400' => $degraded, 'text-foreground' => ! $degraded])>
                                {{ number_format($health['recentFailures']) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-t border-border/60 pt-3 text-xs">
                        <span class="text-muted-foreground">{{ __('dashboard.labels.last_scheduled_run') }}</span>
                        <span class="font-medium text-foreground">{{ $health['lastScheduledRun']?->diffForHumans() ?? __('dashboard.labels.never') }}</span>
                    </div>
                </x-ui.card-content>
            </x-ui.card>

            {{-- Recent Activity Feed --}}
            <x-ui.card class="lg:col-span-2">
                <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                    <div class="flex flex-col gap-1">
                        <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                            <x-lucide-history class="size-4 text-amber-500" />
                            {{ __('dashboard.widgets.activity_feed') }}
                        </x-ui.card-title>
                        <x-ui.card-description class="text-xs">Live audit log events across the application</x-ui.card-description>
                    </div>
                    @can('activity_logs.view')
                        <a href="{{ route('admin.activity-logs.index') }}" wire:navigate
                            class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                            <span>{{ __('dashboard.view_all') }}</span>
                            <x-lucide-arrow-right class="size-3" />
                        </a>
                    @endcan
                </x-ui.card-header>
                <x-ui.card-content class="pt-0">
                    @if ($activity->isNotEmpty())
                        <div class="flex flex-col divide-y divide-border/50">
                            @foreach ($activity as $row)
                                @php($entry = ActivityPresenter::present($row))
                                <div class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $entry['colorClass'] }}">
                                        <x-dynamic-component :component="'lucide-' . $entry['icon']" class="size-4" />
                                    </span>
                                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span class="truncate text-xs font-semibold text-foreground">{{ $entry['title'] }}</span>
                                        <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                            <x-ui.avatar class="size-3.5">
                                                <x-ui.avatar-fallback class="text-[9px]">{{ substr($row->causer?->name ?? 'S', 0, 1) }}</x-ui.avatar-fallback>
                                            </x-ui.avatar>
                                            <span class="truncate font-medium">
                                                {{ $row->causer?->name ?? __('activity_logs.values.system') }}
                                            </span>
                                        </div>
                                    </div>
                                    <span class="shrink-0 font-mono text-[11px] tabular-nums text-muted-foreground bg-muted/50 px-2 py-0.5 rounded-full">
                                        {{ $row->created_at->diffForHumans(null, true) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="py-8 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                    @endif
                </x-ui.card-content>
            </x-ui.card>
        @endcan

    </div>
</div>
