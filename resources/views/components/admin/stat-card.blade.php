@props([
    'label',
    'value',
    'icon',
    'description' => '',
    // Period-over-period change as a percentage. Null renders no trend chip at all —
    // "no comparable previous period" is different from "no change".
    'trend' => null,
    // Set on metrics where a rise is bad news (open tickets, blocked IPs) so the chip
    // colours by meaning rather than by direction.
    'invertTrend' => false,
])

@php
    $hasTrend = $trend !== null;
    $rising = $hasTrend && $trend > 0;
    $flat = $hasTrend && (float) $trend === 0.0;
    $good = $invertTrend ? ! $rising : $rising;

    $trendClass = match (true) {
        ! $hasTrend, $flat => 'text-muted-foreground',
        $good => 'text-emerald-600 dark:text-emerald-400',
        default => 'text-rose-600 dark:text-rose-400',
    };

    $trendIcon = match (true) {
        $flat => 'minus',
        $rising => 'trending-up',
        default => 'trending-down',
    };
@endphp

<x-ui.card>
    <div class="flex items-start justify-between">
        <p class="text-sm font-medium text-muted-foreground">{{ $label }}</p>
        <x-dynamic-component :component="'lucide-' . $icon" class="size-5 shrink-0 text-muted-foreground" />
    </div>
    <div class="mt-3">
        <div class="flex flex-wrap items-baseline gap-2">
            <div class="text-2xl font-bold">{{ is_numeric($value) ? number_format($value) : $value }}</div>

            @if ($hasTrend)
                <span class="inline-flex items-center gap-0.5 text-xs font-medium {{ $trendClass }}">
                    <x-dynamic-component :component="'lucide-' . $trendIcon" class="size-3.5" />
                    {{ $rising ? '+' : '' }}{{ $trend }}%
                </span>
            @endif
        </div>

        @if ($description)
            <p class="mt-0.5 text-xs text-muted-foreground">{{ $description }}</p>
        @endif
    </div>
</x-ui.card>
