{{--
    Generic filter bar for admin index pages.

    Props:
      config            array — keyed filter config from filterBarConfig() (no closures)
      filters           array — current $filters state from the Livewire component
      hasActiveFilters  bool  — whether any filter or search is active
      searchPlaceholder string — placeholder text for the search input
--}}
@props([
    'config' => [],
    'filters' => [],
    'hasActiveFilters' => false,
    'searchPlaceholder' => 'Search…',
])

<div class="flex flex-wrap items-center gap-2">

    {{-- Search --}}
    <div class="relative flex-1 sm:flex-none">
        <x-lucide-search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <x-ui.input
            wire:model.live.debounce.300ms="search"
            placeholder="{{ $searchPlaceholder }}"
            class="h-9 pl-8 sm:w-64"
        />
    </div>

    @foreach($config as $key => $item)
    @php
        $type    = $item['type'] ?? 'multi-select';
        $label   = $item['label'];
        $options = $item['options'] ?? [];
    @endphp

    {{-- ── Multi-select (checkboxes) ── --}}
    @if($type === 'multi-select')
    @php $active = array_values((array)($filters[$key] ?? [])); @endphp
    <div x-data="{ open: false }" class="relative">
        <x-ui.button variant="outline" size="sm" @click="open = !open"
            class="{{ !empty($active) ? 'border-primary/60 bg-primary/5' : '' }}">
            <x-lucide-plus class="size-3.5 {{ !empty($active) ? 'hidden' : '' }}" />
            <x-lucide-check class="size-3.5 {{ !empty($active) ? '' : 'hidden' }}" />
            {{ $label }}
            @foreach($active as $av)
                <span class="ml-0.5 rounded bg-primary/15 px-1.5 py-0.5 text-xs capitalize leading-none">
                    {{ $options[$av] ?? $av }}
                </span>
            @endforeach
        </x-ui.button>
        <div x-show="open" @click.outside="open = false" x-cloak
            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="absolute top-full z-20 mt-1 min-w-[10rem] rounded-md border border-border bg-popover p-1 shadow-md">
            @foreach($options as $val => $optLabel)
            <label class="flex cursor-pointer select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground">
                <input type="checkbox" value="{{ $val }}" wire:model.live="filters.{{ $key }}" class="blat-checkbox cursor-pointer dark:bg-input/30" />
                {{ $optLabel }}
            </label>
            @endforeach
            @if(!empty($active))
            <div class="mt-1 border-t border-border pt-1">
                <button wire:click="setFilter('{{ $key }}', [])" class="w-full rounded-sm py-1 text-center text-xs text-muted-foreground hover:text-foreground">Clear</button>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Single-select (radio) ── --}}
    @elseif($type === 'select')
    @php $active = $filters[$key] ?? ''; @endphp
    <div x-data="{ open: false }" class="relative">
        <x-ui.button variant="outline" size="sm" @click="open = !open"
            class="{{ $active !== '' ? 'border-primary/60 bg-primary/5' : '' }}">
            <x-lucide-plus class="size-3.5 {{ $active !== '' ? 'hidden' : '' }}" />
            <x-lucide-check class="size-3.5 {{ $active !== '' ? '' : 'hidden' }}" />
            {{ $label }}
            @if($active !== '')
            <span class="ml-0.5 rounded bg-primary/15 px-1.5 py-0.5 text-xs leading-none">
                {{ $options[$active] ?? $active }}
            </span>
            @endif
        </x-ui.button>
        <div x-show="open" @click.outside="open = false" x-cloak
            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="absolute top-full z-20 mt-1 min-w-[10rem] rounded-md border border-border bg-popover p-1 shadow-md">
            @foreach($options as $val => $optLabel)
            <label class="flex cursor-pointer select-none items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground">
                <input type="radio" name="{{ $key }}_filter" value="{{ $val }}" wire:model.live="filters.{{ $key }}" class="blat-radio cursor-pointer dark:bg-input/30" />
                {{ $optLabel }}
            </label>
            @endforeach
            @if($active !== '')
            <div class="mt-1 border-t border-border pt-1">
                <button wire:click="setFilter('{{ $key }}', '')" class="w-full rounded-sm py-1 text-center text-xs text-muted-foreground hover:text-foreground">Clear</button>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Date range ── --}}
    @elseif($type === 'date-range')
    @php
        $fromKey     = $item['from_key'];
        $toKey       = $item['to_key'];
        $fromVal     = $filters[$fromKey] ?? '';
        $toVal       = $filters[$toKey] ?? '';
        $isDateActive = $fromVal !== '' || $toVal !== '';
    @endphp
    <div x-data="{ open: false }" class="relative">
        <x-ui.button variant="outline" size="sm" @click="open = !open"
            class="{{ $isDateActive ? 'border-primary/60 bg-primary/5' : '' }}">
            <x-lucide-plus class="size-3.5 {{ $isDateActive ? 'hidden' : '' }}" />
            <x-lucide-check class="size-3.5 {{ $isDateActive ? '' : 'hidden' }}" />
            {{ $label }}
            @if($isDateActive)
            <span class="ml-0.5 rounded bg-primary/15 px-1.5 py-0.5 text-xs leading-none">
                {{ $fromVal ?: '…' }} – {{ $toVal ?: '…' }}
            </span>
            @endif
        </x-ui.button>
        <div x-show="open" @click.outside="open = false" x-cloak
            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="absolute top-full z-20 mt-1 w-60 rounded-md border border-border bg-popover p-3 shadow-md">
            <div class="space-y-2">
                <div>
                    <x-ui.label class="mb-1 text-xs">From</x-ui.label>
                    <x-ui.input type="date" wire:model.live="filters.{{ $fromKey }}" class="h-8 text-xs" />
                </div>
                <div>
                    <x-ui.label class="mb-1 text-xs">To</x-ui.label>
                    <x-ui.input type="date" wire:model.live="filters.{{ $toKey }}" class="h-8 text-xs" />
                </div>
                @if($isDateActive)
                <button wire:click="clearFilters('{{ $fromKey }}', '{{ $toKey }}')" class="w-full rounded-sm py-1 text-center text-xs text-muted-foreground hover:text-foreground">
                    Clear
                </button>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Free-text ── --}}
    @elseif($type === 'text')
    @php $textVal = $filters[$key] ?? ''; @endphp
    <div x-data="{ open: false }" class="relative">
        <x-ui.button variant="outline" size="sm" @click="open = !open; $nextTick(() => open && $refs.{{ $key }}Input.focus())"
            class="{{ $textVal !== '' ? 'border-primary/60 bg-primary/5' : '' }}">
            <x-lucide-plus class="size-3.5 {{ $textVal !== '' ? 'hidden' : '' }}" />
            <x-lucide-check class="size-3.5 {{ $textVal !== '' ? '' : 'hidden' }}" />
            {{ $label }}
            @if($textVal !== '')
            <span class="ml-0.5 rounded bg-primary/15 px-1.5 py-0.5 text-xs leading-none">{{ $textVal }}</span>
            @endif
        </x-ui.button>
        <div x-show="open" @click.outside="open = false" x-cloak
            x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
            class="absolute top-full z-20 mt-1 w-56 rounded-md border border-border bg-popover p-2 shadow-md">
            <x-ui.input x-ref="{{ $key }}Input" wire:model.live.debounce.400ms="filters.{{ $key }}"
                placeholder="{{ $item['placeholder'] ?? $label }}" class="h-8 text-xs" />
            @if($textVal !== '')
            <button wire:click="setFilter('{{ $key }}', '')" class="mt-2 w-full rounded-sm py-1 text-center text-xs text-muted-foreground hover:text-foreground">Clear</button>
            @endif
        </div>
    </div>

    {{-- ── Toggle button ── --}}
    @elseif($type === 'toggle')
    @php
        $activeValue   = $item['active_value'] ?? 'true';
        $isToggleActive = ($filters[$key] ?? '') === $activeValue;
        $nextValue      = $isToggleActive ? '' : $activeValue;
        $toggleLabel    = $isToggleActive ? ($item['active_label'] ?? $label) : $label;
        $toggleIcon     = $item['icon'] ?? null;
    @endphp
    <x-ui.button
        variant="{{ $isToggleActive ? 'secondary' : 'outline' }}"
        size="sm"
        wire:click="setFilter('{{ $key }}', '{{ $nextValue }}')"
    >
        @if($toggleIcon)
        <x-dynamic-component :component="'lucide-'.$toggleIcon" class="size-3.5" />
        @endif
        {{ $toggleLabel }}
    </x-ui.button>

    @endif
    @endforeach

    {{-- Reset all --}}
    @if($hasActiveFilters)
    <x-ui.button variant="ghost" size="sm" wire:click="resetFilters">
        <x-lucide-x class="size-3.5" />
        Reset
    </x-ui.button>
    @endif

</div>
