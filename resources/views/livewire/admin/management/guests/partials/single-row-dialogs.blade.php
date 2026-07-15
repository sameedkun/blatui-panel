{{-- Single-row guest action dialogs, shared by the Guests index and the guest
     profile page. Both host components use HandlesGuestRowActions, so these
     dialogs drive the same methods (and the same audit-log rows) in either place. --}}

<x-admin.reason-dialog
    id="ban-user"
    title="Ban Guest"
    description='Optionally provide a reason. Defaults to "Banned by administrator."'
    model="banReason"
    confirm="confirmBan"
    confirm-label="Ban Guest"
    cancel="$wire.set('banningUserId', null)"
    placeholder="Reason for the ban (optional)"
/>

<x-ui.dialog id="convert-user">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>Convert to App User</x-ui.dialog-title>
            <x-ui.dialog-description>
                This guest becomes a real app user in place — same account, same history.
                They'll receive a password-reset link to set their own credentials.
            </x-ui.dialog-description>
        </x-ui.dialog-header>

        <div class="space-y-4">
            <x-ui.field>
                <x-ui.field-label for="convert-email" required>Email</x-ui.field-label>
                <x-ui.input id="convert-email" type="email" wire:model="convertEmail" placeholder="you@example.com"
                    aria-invalid="{{ $errors->has('convertEmail') ? 'true' : 'false' }}" />
                @error('convertEmail')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="convert-name">Name</x-ui.field-label>
                <x-ui.input id="convert-name" wire:model="convertName" placeholder="Full name" />
            </x-ui.field>
        </div>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false; $wire.set('convertingId', null)">Cancel</x-ui.button>
            <x-ui.button wire:click="confirmConvert">Convert</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>

<x-admin.confirm-dialog
    id="delete-user"
    title="Delete Guest"
    confirm="$wire.delete()"
    cancel="$wire.set('deletingId', null)"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This permanently deletes the guest and all associated data. This <strong>cannot be undone</strong>.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="restore-user"
    title="Restore Guest"
    confirm="$wire.restore()"
    cancel="$wire.set('restoringId', null)"
    confirm-label="Restore"
>
    This will restore the guest's account.
</x-admin.confirm-dialog>

<x-admin.confirm-dialog
    id="force-delete-user"
    title="Permanently Delete"
    confirm="$wire.forceDelete()"
    cancel="$wire.set('forceDeleteId', null)"
    confirm-label="Delete Permanently"
    variant="destructive"
>
    This action <strong>cannot be undone</strong>. The guest and all associated data will be permanently removed.
</x-admin.confirm-dialog>
