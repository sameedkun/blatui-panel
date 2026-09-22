{{-- Confirmation/status dialogs for the Announcements index. Included by
     index.blade — shares its Livewire scope ($wire, $deletingId, etc.). --}}

<x-admin.confirm-dialog
    id="delete-announcement"
    :title="__('announcements.dialogs.delete_title')"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    :confirm-label="__('announcements.actions.delete')"
    variant="destructive"
>
    {{ __('announcements.dialogs.delete_description') }}
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="bulk-delete"
    :title="__('announcements.dialogs.bulk_delete_title', ['count' => count($selectedIds)])"
    confirm="$wire.executeBulkDelete()"
    cancel="$wire.cancelBulkAction()"
    :confirm-label="__('announcements.actions.delete')"
    variant="destructive"
>
    {{ __('announcements.dialogs.bulk_delete_description') }}
</x-admin.confirm-dialog>

{{--
    Push status details — reactively opens whenever $viewingStatusId is set
    (row click, or a redirect from the Form after create/edit-and-send lands
    here with ?status={id} already in the URL). While the announcement is
    still Pending, wire:poll.visible keeps refetching it every 2s so the
    status/id/error update live without a manual refresh; the poll attribute
    disappears from the markup — and Alpine's morph tears the interval down
    with it — the moment the row leaves the Pending state.
--}}
<x-ui.dialog id="announcement-status" :open="$viewingAnnouncement !== null"
    x-init="$watch('open', value => { if (! value) $wire.clearViewingStatus() })">
    <x-ui.dialog-content class="sm:max-w-md">
        {{-- Bare polling element rather than an attribute on the component tag above
             — Blade's component-tag compiler doesn't support @if/@endif inside a
             component's own attribute list. Any element works; wire:poll.visible
             already pauses itself while the dialog (and this div with it) is hidden. --}}
        @if ($viewingAnnouncement && $viewingAnnouncement->push_status === \App\Enum\AnnouncementPushStatus::Pending)
            <div wire:poll.2s.visible="$refresh" class="sr-only" aria-hidden="true"></div>
        @endif

        <x-ui.dialog-header>
            <x-ui.dialog-title>{{ __('announcements.dialogs.status_title') }}</x-ui.dialog-title>
            @if ($viewingAnnouncement)
                <x-ui.dialog-description>{{ $viewingAnnouncement->title }}</x-ui.dialog-description>
            @endif
        </x-ui.dialog-header>

        @if ($viewingAnnouncement)
            <x-admin.announcement-status-details :announcement="$viewingAnnouncement" />

            @if ($viewingAnnouncement->push_status === \App\Enum\AnnouncementPushStatus::Pending)
                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <x-ui.spinner class="size-3.5" />
                    {{ __('announcements.dialogs.watching') }}
                </p>
            @endif
        @endif

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false">{{ __('announcements.actions.close') }}</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
