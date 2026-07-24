{{-- Confirmation/status dialogs for the Notifications index. Included by
     index.blade — shares its Livewire scope ($wire, $deletingId, etc.). --}}

<x-admin.confirm-dialog
    id="delete-notification"
    title="Delete Notification"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete"
    variant="destructive"
>
    This will permanently delete the notification. This action cannot be undone.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    title="Delete {{ count($selectedIds) }} Notifications"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    confirm-label="Delete"
    variant="destructive"
>
    This permanently deletes all selected notifications. This action <strong>cannot be undone</strong>.
</x-admin.confirm-dialog>

{{--
    Push status details — reactively opens whenever $viewingStatusId is set
    (row click, or a redirect from the Form after create/edit-and-send lands
    here with ?status={id} already in the URL). While the notification is
    still Pending, wire:poll.visible keeps refetching it every 2s so the
    status/id/error update live without a manual refresh; the poll attribute
    disappears from the markup — and Alpine's morph tears the interval down
    with it — the moment the row leaves the Pending state.
--}}
<x-ui.dialog id="notification-status" :open="$viewingNotification !== null"
    x-init="$watch('open', value => { if (! value) $wire.clearViewingStatus() })">
    <x-ui.dialog-content class="sm:max-w-md">
        {{-- Bare polling element rather than an attribute on the component tag above
             — Blade's component-tag compiler doesn't support @if/@endif inside a
             component's own attribute list. Any element works; wire:poll.visible
             already pauses itself while the dialog (and this div with it) is hidden. --}}
        @if ($viewingNotification && $viewingNotification->push_status === \App\Enum\NotificationPushStatus::Pending)
            <div wire:poll.2s.visible="$refresh" class="sr-only" aria-hidden="true"></div>
        @endif

        <x-ui.dialog-header>
            <x-ui.dialog-title>Push Notification Status</x-ui.dialog-title>
            @if ($viewingNotification)
                <x-ui.dialog-description>{{ $viewingNotification->title }}</x-ui.dialog-description>
            @endif
        </x-ui.dialog-header>

        @if ($viewingNotification)
            <x-admin.notification-status-details :notification="$viewingNotification" />

            @if ($viewingNotification->push_status === \App\Enum\NotificationPushStatus::Pending)
                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <x-ui.spinner class="size-3.5" />
                    Watching for a status update…
                </p>
            @endif
        @endif

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false">Close</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
