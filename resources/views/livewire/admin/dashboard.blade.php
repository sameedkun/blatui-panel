{{--
    Dashboard shell: horizontal tab bar + shared range control. The active tab is a lazily
    loaded child component, so only the tab you are looking at runs any queries.

    The range picker is x-admin.dropdown, NOT x-ui.select — BlatUI's select teleports its
    panel to <body>, and Livewire's morph then orphans the Alpine scope, leaving the
    control dead (isSelected/seedSelected undefined) until a full page refresh.
--}}
<div class="flex flex-col gap-6">

    {{-- Header with System Operational Pill --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold tracking-tight text-foreground">{{ __('dashboard.title') }}</h1>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ __('dashboard.health.operational') }}
                </span>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ __('dashboard.welcome', ['name' => auth()->user()->name]) }}
            </p>
        </div>

        {{-- Global Controls: Range Picker & Refresh Action --}}
        <div class="flex items-center gap-2">
            <x-admin.dropdown align="end" width="w-48">
                <x-slot:trigger>
                    <x-ui.button variant="outline" size="sm" class="gap-2 shadow-xs">
                        <x-lucide-calendar class="size-3.5 text-muted-foreground" />
                        <span>{{ $rangeLabel }}</span>
                        <x-lucide-chevron-down class="size-3.5 opacity-60" />
                    </x-ui.button>
                </x-slot:trigger>

                @foreach ($rangeOptions as $value => $label)
                    <x-admin.dropdown-item wire:click="selectRange('{{ $value }}')">
                        {{ $label }}
                    </x-admin.dropdown-item>
                @endforeach
            </x-admin.dropdown>

            <x-ui.button variant="outline" size="sm" wire:click="refreshMetrics"
                wire:loading.attr="disabled" wire:target="refreshMetrics" class="shadow-xs">
                <x-lucide-refresh-cw class="size-3.5" wire:loading.class="animate-spin" wire:target="refreshMetrics" />
                <span class="sr-only">{{ __('dashboard.refresh') }}</span>
            </x-ui.button>
        </div>
    </div>

    {{-- Modern Segmented Pill Tab Bar --}}
    <div class="border-b border-border/80 pb-px">
        <nav class="flex flex-wrap items-center gap-1.5" role="tablist">
            @foreach ($tabs as $tab)
                @php($isActive = $tab['key'] === $active['key'])
                <button type="button" role="tab" wire:click="selectTab('{{ $tab['key'] }}')"
                    :aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    @class([
                        'flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-medium transition-all duration-150',
                        'bg-primary text-primary-foreground shadow-xs font-semibold' => $isActive,
                        'text-muted-foreground hover:bg-muted hover:text-foreground' => ! $isActive,
                    ])>
                    <x-dynamic-component :component="'lucide-' . $tab['icon']" class="size-4 shrink-0" />
                    <span>{{ $tab['label'] }}</span>
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Active tab lazily loaded component --}}
    <div>
        @livewire(
            $active['component'],
            ['selectedRange' => $selectedRange],
            key($active['key'] . '-' . $selectedRange)
        )
    </div>

</div>
