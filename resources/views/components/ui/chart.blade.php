@props([
    'type' => 'line',
    'series' => [],
    'options' => [],
    'colors' => [],
    'config' => [],
    'labels' => [],
    'height' => 250,
    'label' => 'Chart',
])

@php
    // shadcn-style config: ['key' => ['label' => '...', 'color' => 'var(--chart-1)']]
    // Derive the colors array + the --color-<key> CSS vars used by labels/legends.
    $resolvedColors = $colors;
    if (empty($resolvedColors) && ! empty($config)) {
        $resolvedColors = array_values(array_filter(array_map(fn ($c) => $c['color'] ?? null, $config)));
    }

    $styleVars = collect($config)
        ->filter(fn ($v) => is_array($v) && isset($v['color']))
        ->map(fn ($v, $k) => "--color-{$k}: {$v['color']};")
        ->implode(' ');

    $payload = [
        'type' => $type,
        'series' => $series,
        'options' => $options,
        'colors' => $resolvedColors,
        'height' => (int) $height,
    ];
    if (! empty($labels)) {
        $payload['labels'] = $labels;
    }
@endphp

<div
    data-slot="chart"
    role="img"
    aria-label="{{ $label }}"
    style="{{ $styleVars }}"
    x-data="shadcnChart({{ \Illuminate\Support\Js::from($payload) }})"
    {{ $attributes->twMerge('flex aspect-video justify-center text-xs w-full [&_.apexcharts-tooltip]:!rounded-lg [&_.apexcharts-tooltip]:!border [&_.apexcharts-tooltip]:!border-border [&_.apexcharts-tooltip]:!bg-popover [&_.apexcharts-tooltip]:!text-popover-foreground [&_.apexcharts-tooltip]:!shadow-xl') }}
>
    {{-- wire:ignore: ApexCharts draws its SVG into this div client-side. Without it a
         Livewire re-render morphs the div back to its empty server markup and the
         chart vanishes until a resize redraws it. New data re-initialises the chart
         through a changed wire:key on a parent instead. --}}
    <div x-ref="canvas" wire:ignore class="w-full" style="min-height: {{ (int) $height }}px"></div>
</div>
