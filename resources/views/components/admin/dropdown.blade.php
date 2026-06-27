{{--
    Morph-safe dropdown for use inside Livewire-rendered loops.

    Unlike BlatUI's dropdown-menu (which teleports to <body> and breaks when a
    Livewire row re-renders), this renders inline and uses position:fixed with
    JS-computed coordinates to escape the table's overflow-hidden — no teleport,
    so Livewire's DOM morphing never orphans the Alpine scope.

    Slots:
      trigger  the clickable element (a button, etc.)
      default  the menu items (use <x-admin.dropdown-item> / <x-admin.dropdown-separator>)

    Props:
      align  'end' (default) right-aligns the menu to the trigger; 'start' left-aligns
      width  Tailwind width class for the panel (default w-48)
--}}
@props(['align' => 'end', 'width' => 'w-48'])

<div
    x-data="{
        open: false,
        x: 0,
        y: 0,
        up: false,
        place() {
            const r = $refs.trigger.getBoundingClientRect();
            this.up = (window.innerHeight - r.bottom) < 240;
            this.x = {{ $align === 'end' ? 'r.right' : 'r.left' }};
            this.y = this.up ? r.top - 4 : r.bottom + 4;
        },
        toggle() {
            if (! this.open) { this.place(); }
            this.open = ! this.open;
        },
    }"
    @scroll.window="open = false"
    @resize.window="open = false"
    @keydown.escape.window="open = false"
    {{ $attributes->merge(['class' => 'inline-block']) }}
>
    <div x-ref="trigger" @click="toggle()">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        @click="open = false"
        :style="`top:${y}px; left:${x}px;`"
        :class="(up ? '-translate-y-full ' : '') + '{{ $align === 'end' ? '-translate-x-full' : '' }}'"
        class="fixed z-50 {{ $width }} rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        {{ $slot }}
    </div>
</div>
