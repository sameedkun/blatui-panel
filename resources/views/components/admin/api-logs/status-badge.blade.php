@props(['code'])

@php
    $classes = match (intdiv((int) $code, 100)) {
        2 => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
        3 => 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
        4 => 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        default => 'bg-rose-500/15 text-rose-700 dark:text-rose-400',
    };
@endphp

{{-- Plain span with x-ui.badge's shape: an attribute bag can't be echoed inside another component's tag. --}}
<span {{ $attributes->twMerge("inline-flex w-fit shrink-0 items-center rounded-md px-2 py-0.5 font-mono text-xs font-medium tabular-nums whitespace-nowrap {$classes}") }}>{{ $code }}</span>
