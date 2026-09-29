{{--
    Collapsible sidebar group: a menu button that expands/collapses a
    sub-menu (chevron rotates), composed from BlatUI's sidebar-menu-item /
    sidebar-menu-button / sidebar-menu-sub. Put <x-ui.sidebar-menu-sub-item>s
    in the slot.

        <x-admin.sidebar-collapsible key="dashboard" icon="layout-dashboard"
            :label="__('navigation.modules.dashboard')" :active="request()->routeIs('admin.dashboard*')">
            <x-ui.sidebar-menu-sub-item>…</x-ui.sidebar-menu-sub-item>
        </x-admin.sidebar-collapsible>

    Behaviour:
      - Starts expanded when one of its pages is active; otherwise remembers the
        viewer's last choice (localStorage — per-browser convenience only).
      - In the icon-collapsed sidebar there is no room for a sub-menu, so a click
        expands the sidebar and opens the group; the parent shows as active there.
      - Uses its own `expanded` flag rather than BlatUI's collapsible (whose `open`
        would shadow the sidebar provider's `open`, which this reads).

    Props:
      key     stable id for the remembered state
      label   button text
      icon    lucide icon name (no `lucide-` prefix)
      active  whether one of the group's pages is the current page
--}}
@props([
    'key',
    'label',
    'icon',
    'active' => false,
])

<x-ui.sidebar-menu-item
    x-data="{
        expanded: {{ $active ? 'true' : 'false' }},
        storageKey: 'sidebar-group:{{ $key }}',
        init() {
            if ({{ $active ? 'true' : 'false' }}) {
                return;
            }
            try {
                this.expanded = localStorage.getItem(this.storageKey) === '1';
            } catch (e) {}
        },
        toggle() {
            if (! open && ! isMobile) {
                open = true;
                this.expanded = true;
            } else {
                this.expanded = ! this.expanded;
            }
            try {
                localStorage.setItem(this.storageKey, this.expanded ? '1' : '0');
            } catch (e) {}
        },
    }"
>
    <x-ui.sidebar-menu-button
        @click="toggle()"
        x-bind:aria-expanded="expanded.toString()"
        x-bind:data-active="(! open && {{ $active ? 'true' : 'false' }}) ? 'true' : null"
        x-bind:data-state="expanded ? 'open' : 'closed'"
    >
        <x-dynamic-component :component="'lucide-'.$icon" />
        <span class="truncate">{{ $label }}</span>
        <x-lucide-chevron-right class="ml-auto transition-transform duration-200" x-bind:class="expanded ? 'rotate-90' : ''" />
    </x-ui.sidebar-menu-button>

    <div x-show="expanded" x-collapse x-cloak>
        <x-ui.sidebar-menu-sub class="mt-1">
            {{ $slot }}
        </x-ui.sidebar-menu-sub>
    </div>
</x-ui.sidebar-menu-item>
