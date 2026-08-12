@php
    use App\Support\ActivityPresenter;

    // x-ui.chart ships with `aspect-video` in its base classes — on a wide card that
    // forces a box hundreds of pixels taller than the chart. Every chart here overrides
    // it with aspect-auto + an explicit height.
    $chartClass = 'aspect-auto h-[260px]';
    $degraded = $health['recentFailures'] > 0;
@endphp

<div class="flex flex-col gap-6">

    {{-- KPI row --}}
    @if (count($cards))
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <x-admin.stat-card :label="$card['label']" :value="$card['value']" :icon="$card['icon']"
                    :description="$card['description']" :trend="$card['trend']"
                    :invert-trend="$card['invert_trend'] ?? false" />
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- Signups --}}
        @can('users.view')
            <x-ui.card class="lg:col-span-2">
                <x-ui.card-header class="pb-4">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                        <x-lucide-user-plus class="size-4 text-muted-foreground" />
                        {{ __('dashboard.widgets.signups') }}
                    </x-ui.card-title>
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
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                        <x-lucide-pie-chart class="size-4 text-muted-foreground" />
                        {{ __('dashboard.widgets.audience_split') }}
                    </x-ui.card-title>
                    <span class="text-xs tabular-nums text-muted-foreground">{{ number_format(array_sum($split['values'])) }}</span>
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

        {{-- Platform health --}}
        @can('activity_logs.view')
            <x-ui.card class="lg:col-span-1">
                <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                        <x-lucide-heart-pulse class="size-4 text-muted-foreground" />
                        {{ __('dashboard.widgets.system_health') }}
                    </x-ui.card-title>
                    <x-ui.badge :variant="$degraded ? 'destructive' : 'secondary'">
                        {{ $degraded ? __('dashboard.health.degraded') : __('dashboard.health.healthy') }}
                    </x-ui.badge>
                </x-ui.card-header>
                <x-ui.card-content class="flex flex-col gap-3 pt-0">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-md border border-border p-3">
                            <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.queued_jobs') }}</p>
                            <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($health['queued']) }}</p>
                        </div>
                        <div class="rounded-md border border-border p-3">
                            <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.reserved_jobs') }}</p>
                            <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($health['reserved']) }}</p>
                        </div>
                        <div class="rounded-md border border-border p-3">
                            <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.failed_jobs') }}</p>
                            <p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($health['failed']) }}</p>
                        </div>
                        <div class="rounded-md border border-border p-3">
                            <p class="text-xs text-muted-foreground">{{ __('dashboard.labels.recent_failures') }}</p>
                            <p @class(['mt-1 text-xl font-bold tabular-nums', 'text-rose-600 dark:text-rose-400' => $degraded])>
                                {{ number_format($health['recentFailures']) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 border-t border-border pt-3 text-xs">
                        <span class="text-muted-foreground">{{ __('dashboard.labels.last_scheduled_run') }}</span>
                        <span class="font-medium">{{ $health['lastScheduledRun']?->diffForHumans() ?? __('dashboard.labels.never') }}</span>
                    </div>
                </x-ui.card-content>
            </x-ui.card>

            {{-- Recent activity --}}
            <x-ui.card class="lg:col-span-2">
                <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                        <x-lucide-history class="size-4 text-muted-foreground" />
                        {{ __('dashboard.widgets.activity_feed') }}
                    </x-ui.card-title>
                    {{-- Gated on the destination's own permission, not the card's — a card and
                         the page it links to don't have to share a gate. --}}
                    @can('activity_logs.view')
                        <a href="{{ route('admin.activity-logs.index') }}" wire:navigate
                            class="text-xs text-muted-foreground hover:text-foreground">{{ __('dashboard.view_all') }}</a>
                    @endcan
                </x-ui.card-header>
                <x-ui.card-content class="pt-0">
                    @if ($activity->isNotEmpty())
                        <div class="flex flex-col divide-y divide-border/50">
                            @foreach ($activity as $row)
                                {{-- Reuses the presenter the activity viewer and every per-record
                                     Activity tab render through, so wording and icons match. --}}
                                @php($entry = ActivityPresenter::present($row))
                                <div class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0">
                                    {{-- colorClass carries a background AND a text colour, so it
                                         needs a sized, rounded container to sit in — same badge
                                         treatment every other Activity tab uses. --}}
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $entry['colorClass'] }}">
                                        <x-dynamic-component :component="'lucide-' . $entry['icon']" class="size-4" />
                                    </span>
                                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span class="truncate text-sm font-medium">{{ $entry['title'] }}</span>
                                        <span class="truncate text-xs text-muted-foreground">
                                            {{ $row->causer?->name ?? __('activity_logs.values.system') }}
                                        </span>
                                    </div>
                                    <span class="shrink-0 text-xs tabular-nums text-muted-foreground">
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
