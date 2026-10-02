{{--
    KPI card — value first, then its trend, then what it measures:
        12,842
        ↑ 12.4%  vs previous period
        Total users
    $prominent (Overview) renders the value larger. Trend colours follow the
    metric's sentiment (a rise in open tickets is red), not its direction.
--}}
@php
    /** @var \App\Support\Dashboard\Blocks\Metric $block */
    $prominent = $prominent ?? false;
    $change = $block->change();
    $sentimentClass = match ($block->sentiment()) {
        'positive' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        'negative' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400',
        default => 'bg-muted text-muted-foreground',
    };
    $directionIcon = match ($block->direction()) {
        'up' => 'arrow-up-right',
        'down' => 'arrow-down-right',
        default => 'minus',
    };
    $showLink = $block->href && (! $block->hrefPermission || auth()->user()?->can($block->hrefPermission));
@endphp

<x-ui.card class="group relative flex h-full flex-col gap-0 p-5 transition-colors {{ $showLink ? 'hover:border-foreground/20' : '' }}">
    <div class="flex items-start justify-between gap-3">
        {{-- min-w-0 + truncate: a long currency value never runs into the icon on narrow cards. --}}
        <p title="{{ $block->formatted() }}" @class([
            'min-w-0 truncate font-semibold tracking-tight tabular-nums text-foreground',
            'text-2xl 2xl:text-3xl' => $prominent,
            'text-2xl' => ! $prominent,
        ])>{{ $block->formatted() }}</p>

        @if ($block->icon)
            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <x-dynamic-component :component="'lucide-'.$block->icon" class="size-4" />
            </div>
        @endif
    </div>

    <div class="mt-1.5 flex min-h-5 flex-wrap items-center gap-1.5 text-xs">
        @if ($change !== null)
            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-medium tabular-nums {{ $sentimentClass }}">
                <x-dynamic-component :component="'lucide-'.$directionIcon" class="size-3" />
                {{ number_format(abs($change), 1) }}%
            </span>
            <span class="text-muted-foreground">{{ __('dashboard.vs_previous') }}</span>
        @endif
    </div>

    <p class="mt-3 text-sm font-medium text-muted-foreground">{{ $block->label }}</p>

    @if ($block->description)
        {{-- Wraps rather than truncates: descriptions carry caveats (e.g. "other currencies not included") that must stay readable. --}}
        <p class="mt-0.5 text-xs leading-snug text-pretty text-muted-foreground/80" title="{{ $block->description }}">{{ $block->description }}</p>
    @endif

    @if ($showLink)
        <a href="{{ $block->href }}" wire:navigate class="absolute inset-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-label="{{ $block->label }}"></a>
    @endif
</x-ui.card>
