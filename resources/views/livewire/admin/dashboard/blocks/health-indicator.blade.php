{{--
    One health line. Rendered inside the Overview's health card grid rather
    than a card of its own — the snapshot is one glance, not five.
--}}
@php
    /** @var \App\Support\Dashboard\Blocks\HealthIndicator $block */
    $states = [
        'ok' => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-700 dark:text-emerald-400'],
        'warning' => ['dot' => 'bg-amber-500', 'text' => 'text-amber-700 dark:text-amber-400'],
        'critical' => ['dot' => 'bg-rose-500 animate-pulse', 'text' => 'text-rose-700 dark:text-rose-400'],
        'unknown' => ['dot' => 'bg-muted-foreground/40', 'text' => 'text-muted-foreground'],
    ];
    $state = $states[$block->status] ?? $states['unknown'];
    $showLink = $block->href && (! $block->hrefPermission || auth()->user()?->can($block->hrefPermission));
@endphp

<div class="relative flex h-full items-start gap-3 rounded-lg border border-border p-3.5 transition-colors {{ $showLink ? 'hover:bg-muted/40' : '' }}">
    <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
        <x-dynamic-component :component="'lucide-'.$block->icon" class="size-4" />
    </span>
    <div class="min-w-0 flex-1">
        <p class="truncate text-xs text-muted-foreground">{{ $block->label }}</p>
        <p class="flex items-center gap-1.5 text-base font-semibold tabular-nums text-foreground">
            <span class="size-2 shrink-0 rounded-full {{ $state['dot'] }}" aria-hidden="true"></span>
            {{ $block->value }}
        </p>
        <p class="truncate text-xs {{ $state['text'] }}">
            {{ __('dashboard.health.states.'.$block->status) }}@if ($block->hint)<span class="text-muted-foreground"> · {{ $block->hint }}</span>@endif
        </p>
    </div>

    @if ($showLink)
        <a href="{{ $block->href }}" wire:navigate class="absolute inset-0 rounded-lg" aria-label="{{ $block->label }}"></a>
    @endif
</div>
