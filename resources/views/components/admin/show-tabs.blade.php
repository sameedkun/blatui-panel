{{--
    Reusable tab strip for detail/profile pages. Opt-in — a page includes this in
    its own view; it is never imposed by BaseShow.

    Drive it from a component using the HasShowTabs trait:
        <x-admin.show-tabs :tabs="$this->availableTabs()" :active="$this->resolveActiveTab()">
            @include($tabs[$active]['view'] ?? '...', [...])
        </x-admin.show-tabs>

    Props:
      tabs    array  — permission-filtered registry (key => ['label', 'icon'?, ...])
      active  string — the active tab key

    The tab buttons call selectTab() on the parent component (provided by HasShowTabs).
    The tab body is whatever you pass as the slot.
--}}
@props([
    'tabs' => [],
    'active' => '',
])

<div class="flex flex-col gap-6">

    {{-- Tab navigation --}}
    <div class="flex items-center gap-1 overflow-x-auto border-b border-border">
        @foreach ($tabs as $key => $tab)
            <button wire:click="selectTab('{{ $key }}')" type="button"
                class="relative -mb-px flex items-center gap-1.5 whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium transition-colors {{ $active === $key ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                @if (! empty($tab['icon']))
                    <x-dynamic-component :component="'lucide-' . $tab['icon']" class="size-4" />
                @endif
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    {{-- Active tab content --}}
    <div wire:key="show-tab-{{ $active }}">
        {{ $slot }}
    </div>

</div>
