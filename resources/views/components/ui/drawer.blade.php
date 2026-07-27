{{--
    id  optional — enables "dispatchable" mode, same pattern as <x-ui.dialog>:
        the drawer also opens/closes from anywhere via $dispatch('open-drawer-{id}')
        / $dispatch('close-drawer-{id}'). Added on top of the upstream BlatUI
        source (which only exposes `direction`) so a drawer can be triggered from
        a row inside a @foreach without living inside that loop itself.
--}}
@props(['direction' => 'bottom', 'id' => null])

<div
    data-slot="drawer"
    data-vaul-drawer-direction="{{ $direction }}"
    x-data="{ open: false, direction: @js($direction) }"
    @if ($id)
        @open-drawer-{{ $id }}.window="open = true"
        @close-drawer-{{ $id }}.window="open = false"
    @endif
    x-id="['blat-drawer']"
    {{ $attributes }}
>
    {{ $slot }}
</div>
