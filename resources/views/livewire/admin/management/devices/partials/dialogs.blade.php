{{-- Confirmation dialogs for the Devices index. Included by index.blade — shares its Livewire scope. --}}

<x-admin.reason-dialog
    id="block-device"
    title="Block Device"
    description="A blocked device is signed out immediately and can never be reactivated by logging in again — only an admin can lift the block."
    model="blockReason"
    confirm="block"
    confirm-label="Block Device"
    label="Reason"
    placeholder="Why is this device being blocked?"
    cancel="$wire.set('blockingUlid', null)"
/>

<x-admin.confirm-dialog
    id="revoke-device"
    title="Revoke Device"
    confirm="$wire.revoke()"
    cancel="$wire.set('revokingUlid', null)"
    confirm-label="Revoke"
    variant="destructive"
>
    This immediately signs the device out. It can log back in again later, subject to the account's device limit.
</x-admin.confirm-dialog>
