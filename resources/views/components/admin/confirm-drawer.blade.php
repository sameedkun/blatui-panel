{{--
    Reusable right-side confirmation drawer (drawer-based equivalent of
    <x-admin.confirm-dialog>, used across Devices/BlockedIps per project
    preference for drawers over dialogs). Single-instance drawers don't hit
    the teleport/morph issue documented for dropdowns/selects inside Livewire
    loops. Open it from anywhere with:
        $dispatch('open-drawer-{id}')

    Props:
      id           drawer id (drives the open/close events)
      title        heading text
      confirm      JS expression run on confirm, e.g. "$wire.revoke()"
      confirmLabel confirm button text (default "Confirm")
      cancel       optional JS expression run on cancel, e.g. "$wire.set('revokingId', null)"
      variant      'default' | 'destructive' (styles the confirm button)
      slot         the description body (supports HTML)
--}}
@props([
    'id',
    'title',
    'confirm',
    'confirmLabel' => 'Confirm',
    'cancel' => '',
    'variant' => 'default',
])

<x-ui.drawer :id="$id" direction="right">
    <x-ui.drawer-content>
        <x-ui.drawer-header>
            <x-ui.drawer-title>{{ $title }}</x-ui.drawer-title>
            <x-ui.drawer-description>{{ $slot }}</x-ui.drawer-description>
        </x-ui.drawer-header>
        <x-ui.drawer-footer class="flex-row justify-end">
            <x-ui.button variant="outline" @click="open = false; {!! $cancel !!}">Cancel</x-ui.button>
            <x-ui.button
                variant="{{ $variant }}"
                @click="open = false"
                wire:click="{!! $confirm !!}"
            >{{ $confirmLabel }}</x-ui.button>
        </x-ui.drawer-footer>
    </x-ui.drawer-content>
</x-ui.drawer>
