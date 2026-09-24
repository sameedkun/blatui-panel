@props(['method'])

@php
    $classes = match (strtoupper($method)) {
        'GET' => 'text-sky-700 dark:text-sky-400',
        'POST' => 'text-emerald-700 dark:text-emerald-400',
        'PUT', 'PATCH' => 'text-amber-700 dark:text-amber-400',
        'DELETE' => 'text-rose-700 dark:text-rose-400',
        default => 'text-muted-foreground',
    };
@endphp

<span {{ $attributes->merge(['class' => "font-mono text-xs font-semibold {$classes}"]) }}>{{ strtoupper($method) }}</span>
