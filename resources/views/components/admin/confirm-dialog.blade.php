{{--
    Reusable confirmation dialog (alert-dialog based).

    Single-instance dialogs don't hit the teleport/morph issue, so this safely
    wraps BlatUI's alert-dialog. Open it from anywhere with:
        $dispatch('open-alert-dialog-{id}')

    Props:
      id           dialog id (drives the open/close events)
      title        heading text
      confirm      JS expression run on confirm, e.g. "$wire.delete()"
      confirmLabel confirm button text (default "Confirm")
      cancel       optional JS expression run on cancel, e.g. "$wire.set('deletingId', null)"
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

<x-ui.alert-dialog :id="$id">
    <x-ui.alert-dialog-content>
        <x-ui.alert-dialog-header>
            <x-ui.alert-dialog-title>{{ $title }}</x-ui.alert-dialog-title>
            <x-ui.alert-dialog-description>{{ $slot }}</x-ui.alert-dialog-description>
        </x-ui.alert-dialog-header>
        <x-ui.alert-dialog-footer>
            <x-ui.alert-dialog-cancel @click="{{ $cancel }}">Cancel</x-ui.alert-dialog-cancel>
            <x-ui.alert-dialog-action
                @click="{{ $confirm }}"
                @class(['bg-destructive text-destructive-foreground hover:bg-destructive/90' => $variant === 'destructive'])
            >{{ $confirmLabel }}</x-ui.alert-dialog-action>
        </x-ui.alert-dialog-footer>
    </x-ui.alert-dialog-content>
</x-ui.alert-dialog>
