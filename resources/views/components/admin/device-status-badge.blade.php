{{--
    Status badge for a device, derived from its timestamps — shared by the
    global Devices table and the User Show Devices tab (same underlying
    component, but kept as its own partial so the color mapping lives in one
    place, same pattern as <x-admin.ticket-status-badge>).

    Props:
      device  App\Models\UserDevice
--}}
@props(['device'])

@if ($device->is_blocked)
    <x-ui.badge variant="destructive">Blocked</x-ui.badge>
@elseif ($device->is_revoked)
    <x-ui.badge variant="secondary">Revoked</x-ui.badge>
@else
    <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">Active</x-ui.badge>
@endif
