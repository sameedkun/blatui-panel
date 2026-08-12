{{--
    Dashboard shell: horizontal tab bar + shared range control. The active tab is a lazily
    loaded child component, so only the tab you are looking at runs any queries.

    The range picker is x-admin.dropdown, NOT x-ui.select — BlatUI's select teleports its
    panel to <body>, and Livewire's morph then orphans the Alpine scope, leaving the
    control dead (isSelected/seedSelected undefined) until a full page refresh.
--}}
<div class="flex flex-col gap-6">

    <x-admin.page-header :title="__('dashboard.title')"
        :description="__('dashboard.welcome', ['name' => auth()->user()->name])" />

    {{-- Tab bar + range --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border">
        <nav class="-mb-px flex flex-wrap items-center gap-1" role="tablist">
            @foreach ($tabs as $tab)
                <button type="button" role="tab" wire:click="selectTab('{{ $tab['key'] }}')"
                    :aria-selected="{{ $tab['key'] === $active['key'] ? 'true' : 'false' }}"
                    @class([
                        'flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                        'border-primary text-foreground' => $tab['key'] === $active['key'],
                        'border-transparent text-muted-foreground hover:border-border hover:text-foreground' => $tab['key'] !== $active['key'],
                    ])>
                    <x-dynamic-component :component="'lucide-' . $tab['icon']" class="size-4 shrink-0" />
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </nav>

        <div class="flex items-center gap-2 pb-2">
            <x-admin.dropdown align="end" width="w-48">
                <x-slot:trigger>
                    <x-ui.button variant="outline" size="sm">
                        <x-lucide-calendar class="size-4" />
                        {{ $rangeLabel }}
                        <x-lucide-chevron-down class="size-4 opacity-60" />
                    </x-ui.button>
                </x-slot:trigger>

                @foreach ($rangeOptions as $value => $label)
                    <x-admin.dropdown-item wire:click="selectRange('{{ $value }}')">
                        {{ $label }}
                    </x-admin.dropdown-item>
                @endforeach
            </x-admin.dropdown>

            <x-ui.button variant="outline" size="sm" wire:click="refreshMetrics"
                wire:loading.attr="disabled" wire:target="refreshMetrics">
                <x-lucide-refresh-cw class="size-4" wire:loading.class="animate-spin" wire:target="refreshMetrics" />
                <span class="sr-only">{{ __('dashboard.refresh') }}</span>
            </x-ui.button>
        </div>
    </div>

    {{-- Active tab. Each tab component carries #[Lazy] itself, so it renders a skeleton
         first and fetches its data in its own request. The key pins both tab and range so
         switching either remounts the child rather than morphing one tab into another. --}}
    <div>
        @livewire(
            $active['component'],
            ['selectedRange' => $selectedRange],
            key($active['key'] . '-' . $selectedRange)
        )
    </div>

</div>
