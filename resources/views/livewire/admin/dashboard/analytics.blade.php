@php
    // x-ui.chart's base classes include `aspect-video`, which on a wide card forces a box
    // far taller than the chart itself. Overridden here with an explicit height.
    $chartClass = 'aspect-auto h-[260px]';
    $donutClass = 'aspect-auto h-[240px]';
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

    @if ($revenue)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-banknote class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.revenue_trend') }}
                </x-ui.card-title>
                <span class="text-xs tabular-nums text-muted-foreground">${{ number_format(array_sum($revenue['values']), 2) }}</span>
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

    @if ($churn)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-activity class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.churn') }}
                </x-ui.card-title>
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

    @if ($tickets)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-message-square class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.ticket_volume') }}
                </x-ui.card-title>
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

    @if ($devices)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-radio class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.device_registrations') }}
                </x-ui.card-title>
                <span class="text-xs tabular-nums text-muted-foreground">{{ number_format(array_sum($devices['values'])) }}</span>
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

    @if ($deviceTypes)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-smartphone class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.device_types') }}
                </x-ui.card-title>
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

    @if ($platforms)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-monitor class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.platforms') }}
                </x-ui.card-title>
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

    @if ($countries !== null)
        <x-ui.card>
            <x-ui.card-header class="pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-globe class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.countries') }}
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content class="pt-0">
                @if (count($countries))
                    @php($busiest = collect($countries)->max('total') ?: 1)
                    <div class="flex flex-col gap-3">
                        @foreach ($countries as $row)
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="truncate font-medium">{{ $row['country'] }}</span>
                                    <span class="shrink-0 tabular-nums text-muted-foreground">{{ number_format($row['total']) }}</span>
                                </div>
                                <x-ui.progress :value="round($row['total'] / $busiest * 100)" class="h-1.5" />
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- No geo-IP package is installed; country is only ever client-reported
                         at device registration, so an empty list just means none sent one. --}}
                    <p class="py-16 text-center text-xs text-muted-foreground">{{ __('dashboard.no_data') }}</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    @endif

    @if ($contexts)
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-chart-column class="size-4 text-muted-foreground" />
                    {{ __('dashboard.widgets.activity_contexts') }}
                </x-ui.card-title>
                <span class="text-xs tabular-nums text-muted-foreground">{{ number_format(array_sum($contexts['values'])) }}</span>
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
