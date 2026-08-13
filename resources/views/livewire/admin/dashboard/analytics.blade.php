@php
    // x-ui.chart's base classes include `aspect-video` — overridden with an explicit height.
    $chartClass = 'aspect-auto h-[260px]';
    $donutClass = 'aspect-auto h-[240px]';
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Revenue Trend --}}
    @if ($revenue)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-banknote class="size-4 text-emerald-500" />
                        {{ __('dashboard.widgets.revenue_trend') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Gross revenue over the selected window</x-ui.card-description>
                </div>
                <x-ui.badge variant="default" class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 font-mono text-xs font-semibold">
                    ${{ number_format(array_sum($revenue['values']), 2) }}
                </x-ui.badge>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($revenue['values']) > 0)
                    {{-- No yaxis.formatter here: ApexCharts calls it as a function, so an
                         explicit null throws "r is not a function" during render. --}}
                    <x-ui.chart type="area" height="260" :class="$chartClass"
                        :label="__('dashboard.widgets.revenue_trend')"
                        :series="[['name' => __('dashboard.series.revenue'), 'data' => $revenue['values']]]"
                        :colors="['var(--chart-2)']"
                        :options="[
                            'chart' => ['toolbar' => ['show' => false]],
                            'stroke' => ['width' => 2, 'curve' => 'smooth'],
                            'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.45, 'opacityTo' => 0.05, 'stops' => [5, 95]]],
                            'dataLabels' => ['enabled' => false],
                            'xaxis' => ['categories' => $revenue['labels'], 'tickAmount' => 6],
                            'legend' => ['show' => false],
                            'grid' => ['strokeDashArray' => 4],
                        ]" />
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Subscriber Churn --}}
    @if ($churn)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-activity class="size-4 text-primary" />
                        {{ __('dashboard.widgets.churn') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">New subscriptions vs cancellations</x-ui.card-description>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.badge variant="outline" class="text-xs">
                        Net: {{ number_format(array_sum($churn['new']) - array_sum($churn['cancelled'])) }}
                    </x-ui.badge>
                </div>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($churn['new']) > 0 || array_sum($churn['cancelled']) > 0)
                    <x-ui.chart type="bar" height="260" :class="$chartClass"
                        :label="__('dashboard.widgets.churn')"
                        :series="[
                            ['name' => __('dashboard.series.new'), 'data' => $churn['new']],
                            ['name' => __('dashboard.series.cancelled'), 'data' => $churn['cancelled']],
                        ]"
                        :colors="['var(--chart-2)', 'var(--chart-5)']"
                        :options="[
                            'chart' => ['toolbar' => ['show' => false]],
                            'plotOptions' => ['bar' => ['borderRadius' => 3, 'columnWidth' => '60%']],
                            'dataLabels' => ['enabled' => false],
                            'xaxis' => ['categories' => $churn['labels'], 'tickAmount' => 6],
                            'legend' => ['show' => true, 'position' => 'bottom'],
                            'grid' => ['strokeDashArray' => 4],
                        ]" />
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Ticket Volume --}}
    @if ($tickets)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                    <x-lucide-message-square class="size-4 text-blue-500" />
                    {{ __('dashboard.widgets.ticket_volume') }}
                </x-ui.card-title>
                <x-ui.card-description class="text-xs">Support requests opened vs resolved</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($tickets['opened']) > 0 || array_sum($tickets['closed']) > 0)
                    <x-ui.chart type="line" height="260" :class="$chartClass"
                        :label="__('dashboard.widgets.ticket_volume')"
                        :series="[
                            ['name' => __('dashboard.series.opened'), 'data' => $tickets['opened']],
                            ['name' => __('dashboard.series.closed'), 'data' => $tickets['closed']],
                        ]"
                        :colors="['var(--chart-4)', 'var(--chart-2)']"
                        :options="[
                            'chart' => ['toolbar' => ['show' => false]],
                            'stroke' => ['width' => 2, 'curve' => 'smooth'],
                            'dataLabels' => ['enabled' => false],
                            'markers' => ['size' => 0, 'hover' => ['size' => 4]],
                            'xaxis' => ['categories' => $tickets['labels'], 'tickAmount' => 6],
                            'legend' => ['show' => true, 'position' => 'bottom'],
                            'grid' => ['strokeDashArray' => 4],
                        ]" />
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Device Registrations --}}
    @if ($devices)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-radio class="size-4 text-purple-500" />
                        {{ __('dashboard.widgets.device_registrations') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">New user device bindings</x-ui.card-description>
                </div>
                <x-ui.badge variant="outline" class="font-mono text-xs tabular-nums">
                    Total: {{ number_format(array_sum($devices['values'])) }}
                </x-ui.badge>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($devices['values']) > 0)
                    <x-ui.chart type="area" height="260" :class="$chartClass"
                        :label="__('dashboard.widgets.device_registrations')"
                        :series="[['name' => __('dashboard.series.devices'), 'data' => $devices['values']]]"
                        :colors="['var(--chart-4)']"
                        :options="[
                            'chart' => ['toolbar' => ['show' => false]],
                            'stroke' => ['width' => 2, 'curve' => 'smooth'],
                            'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 1, 'opacityFrom' => 0.45, 'opacityTo' => 0.05, 'stops' => [5, 95]]],
                            'dataLabels' => ['enabled' => false],
                            'xaxis' => ['categories' => $devices['labels'], 'tickAmount' => 5],
                            'legend' => ['show' => false],
                            'grid' => ['strokeDashArray' => 4],
                        ]" />
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Device Types --}}
    @if ($deviceTypes)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                    <x-lucide-smartphone class="size-4 text-indigo-500" />
                    {{ __('dashboard.widgets.device_types') }}
                </x-ui.card-title>
                <x-ui.card-description class="text-xs">Form factor breakdown (Mobile vs Desktop)</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (array_sum($deviceTypes['values']) > 0)
                    <x-ui.chart type="donut" height="240" :class="$donutClass"
                        :label="__('dashboard.widgets.device_types')"
                        :labels="$deviceTypes['labels']" :series="$deviceTypes['values']"
                        :colors="['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)']"
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

    {{-- Operating Systems / Platforms --}}
    @if ($platforms)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                    <x-lucide-monitor class="size-4 text-teal-500" />
                    {{ __('dashboard.widgets.platforms') }}
                </x-ui.card-title>
                <x-ui.card-description class="text-xs">Top client operating systems</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (count($platforms['labels']))
                    <x-ui.chart type="bar" height="240" :class="$donutClass"
                        :label="__('dashboard.widgets.platforms')"
                        :series="[['name' => __('dashboard.series.devices'), 'data' => $platforms['values']]]"
                        :colors="['var(--chart-2)']"
                        :options="[
                            'chart' => ['toolbar' => ['show' => false]],
                            'plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4, 'barHeight' => '60%']],
                            'dataLabels' => ['enabled' => true],
                            'xaxis' => ['categories' => $platforms['labels']],
                            'legend' => ['show' => false],
                            'grid' => ['strokeDashArray' => 4],
                        ]" />
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Top Countries --}}
    @if ($countries !== null)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                    <x-lucide-globe class="size-4 text-blue-500" />
                    {{ __('dashboard.widgets.countries') }}
                </x-ui.card-title>
                <x-ui.card-description class="text-xs">Client device geographic distribution</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (count($countries))
                    @php($busiest = collect($countries)->max('total') ?: 1)
                    <div class="flex flex-col gap-3.5">
                        @foreach ($countries as $row)
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="truncate font-semibold text-foreground">{{ $row['country'] }}</span>
                                    <span class="shrink-0 font-mono font-medium tabular-nums text-muted-foreground">{{ number_format($row['total']) }}</span>
                                </div>
                                <x-ui.progress :value="round($row['total'] / $busiest * 100)" class="h-2" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    {{-- Activity Origins --}}
    @if ($contexts)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <div class="flex flex-col gap-1">
                    <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                        <x-lucide-chart-column class="size-4 text-orange-500" />
                        {{ __('dashboard.widgets.activity_contexts') }}
                    </x-ui.card-title>
                    <x-ui.card-description class="text-xs">Audit events by origin runtime</x-ui.card-description>
                </div>
                <x-ui.badge variant="outline" class="font-mono text-xs tabular-nums">
                    Total: {{ number_format(array_sum($contexts['values'])) }}
                </x-ui.badge>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (count($contexts['labels']))
                    <x-ui.chart type="donut" height="240" :class="$donutClass"
                        :label="__('dashboard.widgets.activity_contexts')"
                        :labels="$contexts['labels']" :series="$contexts['values']"
                        :colors="['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)']"
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

</div>
