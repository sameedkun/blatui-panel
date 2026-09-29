{{--
    Range picker + Refresh, shared by Overview and Analytics (HasDashboardRange).
    x-admin.dropdown rather than x-ui.select/dropdown-menu: those teleport to
    <body> and die under Livewire's morph (.ai/rules/views.md).

    Expects: $selectedRange (DateRange), $rangeOptions (key => label).
--}}
<x-admin.dropdown align="end" width="w-44">
    <x-slot:trigger>
        <x-ui.button variant="outline" size="sm" class="gap-2">
            <x-lucide-calendar class="size-3.5 text-muted-foreground" />
            <span>{{ $selectedRange->label() }}</span>
            <x-lucide-chevron-down class="size-3.5 opacity-60" />
        </x-ui.button>
    </x-slot:trigger>

    @foreach ($rangeOptions as $value => $label)
        <x-admin.dropdown-item wire:click="selectRange('{{ $value }}')">
            {{ $label }}
            @if ($value === $selectedRange->key)
                <x-lucide-check class="ml-auto size-3.5" />
            @endif
        </x-admin.dropdown-item>
    @endforeach
</x-admin.dropdown>

{{-- A native title, not x-admin.tooltip: that tooltip is centred over its trigger and,
     with the button pinned to the right edge, its (hidden) box overflowed the viewport
     and gave the whole page a horizontal scrollbar. --}}
<x-ui.button variant="outline" size="sm" wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh"
    aria-label="{{ __('dashboard.refresh') }}" title="{{ __('dashboard.refresh_hint') }}">
    <x-lucide-refresh-cw class="size-3.5" wire:loading.class="animate-spin" wire:target="refresh" />
    <span class="hidden sm:inline">{{ __('dashboard.refresh') }}</span>
</x-ui.button>
