{{--
    Morph-safe CSS-only tooltip for use inside Livewire-rendered content.

    BlatUI's x-ui.tooltip teleports to <body> and breaks when a Livewire row
    re-renders (orphaned Alpine scope → `open` resolves to window.open →
    "Illegal invocation"). This uses pure group-hover, no Alpine, no teleport.

    Props:
      text      tooltip text
      side      top (default) | bottom | left | right
      tipClass  extra classes for the tooltip bubble (e.g. font-mono)
--}}
@props(['text' => '', 'side' => 'top', 'tipClass' => ''])

@php
    $pos = match ($side) {
        'bottom' => 'top-full mt-2 left-1/2 -translate-x-1/2',
        'left' => 'right-full mr-2 top-1/2 -translate-y-1/2',
        'right' => 'left-full ml-2 top-1/2 -translate-y-1/2',
        default => 'bottom-full mb-2 left-1/2 -translate-x-1/2',
    };
@endphp

<span class="group/tt relative inline-flex">
    {{ $slot }}
    <span
        role="tooltip"
        class="pointer-events-none absolute {{ $pos }} z-[60] w-max whitespace-nowrap rounded-md bg-primary px-2 py-1 text-xs text-primary-foreground opacity-0 shadow-md transition-opacity duration-150 group-hover/tt:opacity-100 {{ $tipClass }}"
    >{{ $text }}</span>
</span>
