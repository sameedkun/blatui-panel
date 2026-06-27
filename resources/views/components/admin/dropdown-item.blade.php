@props(['href' => null, 'variant' => 'default'])

@php
    $base = 'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm outline-none transition-colors cursor-pointer';
    $tone = $variant === 'destructive'
        ? 'text-destructive hover:bg-destructive/10 focus:bg-destructive/10'
        : 'hover:bg-accent hover:text-accent-foreground focus:bg-accent';
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => "$base $tone"]) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->merge(['class' => "$base $tone"]) }}>{{ $slot }}</button>
@endif
