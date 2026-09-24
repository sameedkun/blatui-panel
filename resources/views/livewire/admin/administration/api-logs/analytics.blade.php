@php
    /** @var \App\Support\ApiLogs\AnalyticsRange $selected */
    // x-ui.chart ships `aspect-video`; override with an explicit height (see .ai/rules/views.md).
    $chartClass = 'aspect-auto h-[280px]';
    $ms = fn (?float $value): string => $value === null ? '—' : __('api_logs.ms', ['value' => number_format($value)]);
    $rawOnlyNote = __('api_logs.analytics.raw_only', ['days' => config('api_logs.retention.raw_days')]);
    $hasTraffic = $kpis['requests'] > 0;
@endphp

<div class="flex flex-col gap-6" @if ($selected->isLive()) wire:poll.15s @endif>

    {{-- Header + range picker --}}
    <x-admin.page-header :title="__('api_logs.analytics.title')" :description="__('api_logs.analytics.subtitle')"
        :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('api_logs.title')], ['label' => __('navigation.modules.api_log_analytics')]]">
        <x-slot:actions>
            @if ($selected->isLive())
                <span class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    <span class="size-2 animate-pulse rounded-full bg-emerald-500"></span>
                    {{ __('api_logs.analytics.live') }}
                </span>
            @endif

            <x-admin.dropdown align="end" width="w-48">
                <x-slot:trigger>
                    <x-ui.button variant="outline" size="sm" class="gap-2">
                        <x-lucide-calendar class="size-3.5 text-muted-foreground" />
                        <span>{{ $selected->label() }}</span>
                        <x-lucide-chevron-down class="size-3.5 opacity-60" />
                    </x-ui.button>
                </x-slot:trigger>

                @foreach ($rangeOptions as $value => $label)
                    <x-admin.dropdown-item wire:click="selectRange('{{ $value }}')">
                        {{ $label }}
                        @if ($value === $selected->key)
                            <x-lucide-check class="ml-auto size-3.5" />
                        @endif
                    </x-admin.dropdown-item>
                @endforeach
            </x-admin.dropdown>

            @can('api_logs.requests.view')
                <x-ui.button variant="outline" size="sm" href="{{ route('admin.api-logs.requests.index') }}" wire:navigate>
                    <x-lucide-list class="size-4" />
                    {{ __('navigation.modules.api_log_requests') }}
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 2xl:grid-cols-6">
        <x-admin.stat-card :label="__('api_logs.analytics.kpis.requests')" :value="$kpis['requests']" icon="activity"
            :description="__('api_logs.analytics.kpis.per_minute', ['count' => number_format($kpis['per_minute'], 1)])" />
        <x-admin.stat-card :label="__('api_logs.analytics.kpis.errors_5xx')" :value="$kpis['rate_5xx'].'%'" icon="server-crash"
            :description="__('api_logs.analytics.kpis.of_requests', ['count' => number_format($kpis['errors_5xx'])])" />
        <x-admin.stat-card :label="__('api_logs.analytics.kpis.errors_4xx')" :value="$kpis['rate_4xx'].'%'" icon="triangle-alert"
            :description="__('api_logs.analytics.kpis.of_requests', ['count' => number_format($kpis['errors_4xx'])])" />
        <x-admin.stat-card :label="__('api_logs.analytics.kpis.latency')" :value="$ms($kpis['p95_ms'])" icon="timer"
            :description="__('api_logs.analytics.kpis.latency_hint', ['p50' => $ms($kpis['p50_ms']), 'p99' => $ms($kpis['p99_ms'])])" />
        <x-admin.stat-card :label="__('api_logs.analytics.kpis.avg_latency')" :value="$ms($kpis['avg_ms'])" icon="gauge"
            :description="__('api_logs.analytics.kpis.max_hint', ['max' => $ms($kpis['max_ms'])])" />
        <x-admin.stat-card :label="__('api_logs.analytics.kpis.users')" :value="$kpis['unique_users'] ?? '—'" icon="users"
            :description="$kpis['unique_users'] === null ? __('api_logs.analytics.kpis.users_unavailable') : __('api_logs.analytics.kpis.response_size').': '.\App\Models\ApiLog\ApiRequestLog::formatBytes($kpis['avg_response_bytes'] === null ? null : (int) $kpis['avg_response_bytes'])" />
    </div>

    {{-- Time series --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                    <x-lucide-chart-column class="size-4 text-muted-foreground" />
                    {{ __('api_logs.analytics.charts.requests_over_time') }}
                </x-ui.card-title>
                <x-ui.card-description class="text-xs">{{ __('api_logs.analytics.charts.requests_over_time_hint') }}</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if ($hasTraffic)
                    <div wire:key="chart-requests-{{ md5(json_encode($requestsOverTime)) }}">
                        <x-ui.chart type="bar" height="280" :class="$chartClass"
                            :label="__('api_logs.analytics.charts.requests_over_time')"
                            :series="collect([2, 3, 4, 5])->map(fn (int $class): array => ['name' => __('api_logs.analytics.series.'.$class.'xx'), 'data' => $requestsOverTime['series'][$class]])->all()"
                            :colors="['var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)']"
                            :options="[
                                'chart' => ['stacked' => true, 'toolbar' => ['show' => false]],
                                'plotOptions' => ['bar' => ['columnWidth' => '75%']],
                                'dataLabels' => ['enabled' => false],
                                'xaxis' => ['categories' => $requestsOverTime['labels'], 'tickAmount' => 8],
                                'legend' => ['show' => true, 'position' => 'bottom'],
                                'grid' => ['strokeDashArray' => 4],
                            ]" />
                    </div>
                @else
                    <p class="py-24 text-center text-xs text-muted-foreground">{{ __('api_logs.analytics.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                    <x-lucide-timer class="size-4 text-muted-foreground" />
                    {{ __('api_logs.analytics.charts.latency_over_time') }}
                </x-ui.card-title>
                <x-ui.card-description class="text-xs">{{ __('api_logs.analytics.charts.latency_over_time_hint') }} · {{ __('api_logs.analytics.approximate') }}</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if ($hasTraffic)
                    <div wire:key="chart-latency-{{ md5(json_encode($latencyOverTime)) }}">
                        <x-ui.chart type="line" height="280" :class="$chartClass"
                            :label="__('api_logs.analytics.charts.latency_over_time')"
                            :series="[
                                ['name' => __('api_logs.analytics.series.avg'), 'data' => $latencyOverTime['avg']],
                                ['name' => __('api_logs.analytics.series.p50'), 'data' => $latencyOverTime['p50']],
                                ['name' => __('api_logs.analytics.series.p95'), 'data' => $latencyOverTime['p95']],
                            ]"
                            :colors="['var(--chart-1)', 'var(--chart-2)', 'var(--chart-5)']"
                            :options="[
                                'chart' => ['toolbar' => ['show' => false]],
                                'stroke' => ['width' => 2, 'curve' => 'smooth'],
                                'dataLabels' => ['enabled' => false],
                                'xaxis' => ['categories' => $latencyOverTime['labels'], 'tickAmount' => 8],
                                'legend' => ['show' => true, 'position' => 'bottom'],
                                'grid' => ['strokeDashArray' => 4],
                            ]" />
                    </div>
                @else
                    <p class="py-24 text-center text-xs text-muted-foreground">{{ __('api_logs.analytics.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    {{-- Breakdowns --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.card>
            <x-ui.card-header class="pb-3">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                    <x-lucide-chart-pie class="size-4 text-muted-foreground" />
                    {{ __('api_logs.analytics.charts.status_codes') }}
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content>
                @if ($statusCodes === [])
                    <p class="py-6 text-center text-xs text-muted-foreground">{{ __('api_logs.analytics.no_data') }}</p>
                @else
                    <div wire:key="chart-status-{{ md5(json_encode($statusCodes)) }}">
                        <x-ui.chart type="donut" height="220" class="aspect-auto h-[220px]"
                            :label="__('api_logs.analytics.charts.status_codes')"
                            :labels="array_map('strval', array_keys($statusCodes))" :series="array_values($statusCodes)"
                            :colors="collect(array_keys($statusCodes))->map(fn (int $code): string => match (intdiv($code, 100)) { 2 => 'var(--chart-2)', 3 => 'var(--chart-3)', 4 => 'var(--chart-4)', default => 'var(--chart-5)' })->all()"
                            :options="[
                                'legend' => ['show' => true, 'position' => 'bottom'],
                                'dataLabels' => ['enabled' => false],
                                'stroke' => ['width' => 0],
                                'plotOptions' => ['pie' => ['donut' => ['size' => '68%']]],
                            ]" />
                    </div>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        @include('livewire.admin.administration.api-logs.analytics.bar-list', ['items' => $methods, 'title' => __('api_logs.analytics.charts.methods'), 'icon' => 'arrow-left-right', 'mono' => true])
        @include('livewire.admin.administration.api-logs.analytics.bar-list', ['items' => $versions, 'title' => __('api_logs.analytics.charts.versions'), 'icon' => 'layers', 'mono' => true])
        @include('livewire.admin.administration.api-logs.analytics.bar-list', [
            'items' => $clientTypes === null ? null : collect($clientTypes)->mapWithKeys(fn (int $count, string $type): array => [__('api_logs.client_types.'.$type) => $count])->all(),
            'title' => __('api_logs.analytics.charts.client_types'),
            'icon' => 'monitor-smartphone',
            'note' => $rawOnlyNote,
        ])
    </div>

    {{-- Endpoints --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @include('livewire.admin.administration.api-logs.analytics.endpoint-table', ['rows' => $slowest, 'title' => __('api_logs.analytics.tables.slowest'), 'icon' => 'hourglass', 'columns' => ['requests', 'avg', 'p95', 'max']])
        @include('livewire.admin.administration.api-logs.analytics.endpoint-table', ['rows' => $erroring, 'title' => __('api_logs.analytics.tables.erroring'), 'icon' => 'shield-alert', 'columns' => ['requests', 'errors_4xx', 'errors_5xx', 'error_rate']])
    </div>

    @include('livewire.admin.administration.api-logs.analytics.endpoint-table', ['rows' => $busiest, 'title' => __('api_logs.analytics.tables.busiest'), 'icon' => 'zap', 'columns' => ['requests', 'avg', 'p95', 'errors_4xx', 'errors_5xx', 'error_rate']])

    {{-- Exceptions --}}
    <x-ui.card>
        <x-ui.card-header class="pb-3">
            <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                <x-lucide-bug class="size-4 text-muted-foreground" />
                {{ __('api_logs.analytics.tables.exceptions') }}
            </x-ui.card-title>
            <x-ui.card-description class="text-xs">{{ __('api_logs.analytics.exceptions_window', ['days' => config('api_logs.retention.exception_days')]) }}</x-ui.card-description>
        </x-ui.card-header>
        <x-ui.card-content class="px-0">
            @if ($topExceptions === [])
                <p class="py-6 text-center text-xs text-muted-foreground">{{ __('api_logs.analytics.no_data') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-y border-border bg-muted/40 text-muted-foreground">
                                <th class="px-4 py-2 text-left font-medium">{{ __('api_logs.analytics.columns.exception') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ __('api_logs.analytics.columns.occurrences') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ __('api_logs.analytics.columns.last_seen') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($topExceptions as $exception)
                                <tr wire:key="top-exception-{{ $exception['fingerprint'] }}" class="hover:bg-muted/30">
                                    <td class="max-w-xl px-4 py-2">
                                        <a href="{{ route('admin.api-logs.requests.show', ['requestId' => $exception['sample_request_id'], 'tab' => 'exceptions']) }}" wire:navigate class="block hover:underline">
                                            <span class="block truncate font-mono text-rose-700 dark:text-rose-400">{{ $exception['class'] }}</span>
                                            <span class="block truncate">{{ $exception['message'] }}</span>
                                            @if ($exception['file'])
                                                <span class="block truncate font-mono text-[11px] text-muted-foreground">{{ $exception['file'] }}:{{ $exception['line'] }}</span>
                                            @endif
                                        </a>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono tabular-nums">{{ number_format($exception['occurrences']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-muted-foreground"><x-ui.local-time :value="$exception['last_seen']" show-diff="true" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

    {{-- Raw-only breakdowns --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        @include('livewire.admin.administration.api-logs.analytics.bar-list', [
            'items' => $errorCodes,
            'title' => __('api_logs.analytics.tables.error_codes'),
            'icon' => 'message-square-warning',
            'note' => $rawOnlyNote,
            'mono' => true,
        ])
        @include('livewire.admin.administration.api-logs.analytics.bar-list', [
            'items' => $topIps,
            'title' => __('api_logs.analytics.tables.ips'),
            'icon' => 'network',
            'note' => $rawOnlyNote,
            'mono' => true,
            'links' => collect($topIps ?? [])->mapWithKeys(fn (int $count, string $ip): array => [$ip => route('admin.api-logs.requests.index', ['ip' => $ip])])->all(),
        ])
        @include('livewire.admin.administration.api-logs.analytics.bar-list', [
            'items' => $topUsers === null ? null : collect($topUsers)->mapWithKeys(fn (array $user): array => [($user['email'] ?? '#'.$user['user_id']) => $user['requests']])->all(),
            'title' => __('api_logs.analytics.tables.users'),
            'icon' => 'users',
            'note' => $rawOnlyNote,
            'links' => collect($topUsers ?? [])->mapWithKeys(fn (array $user): array => [($user['email'] ?? '#'.$user['user_id']) => route('admin.api-logs.requests.index', ['user_id' => $user['user_id']])])->all(),
        ])
    </div>

</div>
