{{--
    Card shell shared by every dashboard block: icon + title, an optional
    description, a right-hand aside (headline figure) and a permission-gated
    "View all" link. The block's own content goes in the slot.
--}}
@props([
    'title',
    'description' => null,
    'icon' => null,
    'href' => null,
    'hrefPermission' => null,
])

@php
    $showLink = $href && (! $hrefPermission || auth()->user()?->can($hrefPermission));
    // Resolved up front: a `{{ $attributes… }}` inside a component tag is parsed by
    // Blade as an attribute-bag binding, not as text.
    $cardClass = trim('flex h-full flex-col gap-4 p-5 '.$attributes->get('class', ''));
@endphp

<x-ui.card :class="$cardClass">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="flex items-center gap-2 text-sm font-semibold text-foreground">
                @if ($icon)
                    <x-dynamic-component :component="'lucide-'.$icon" class="size-4 shrink-0 text-muted-foreground" />
                @endif
                <span class="truncate">{{ $title }}</span>
            </h3>
            @if ($description)
                <p class="mt-1 text-xs text-muted-foreground">{{ $description }}</p>
            @endif
        </div>

        @if (isset($aside) || $showLink)
            <div class="flex shrink-0 items-center gap-3">
                {{ $aside ?? '' }}

                @if ($showLink)
                    <a href="{{ $href }}" wire:navigate
                        class="inline-flex items-center gap-0.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground">
                        {{ __('dashboard.view_all') }}
                        <x-lucide-chevron-right class="size-3.5" />
                    </a>
                @endif
            </div>
        @endif
    </div>

    <div class="min-w-0 flex-1">
        {{ $slot }}
    </div>
</x-ui.card>
