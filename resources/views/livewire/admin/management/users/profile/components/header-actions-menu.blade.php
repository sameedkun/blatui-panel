{{--
    Secondary action items for the profile header's "Actions" menu — shared by
    the desktop menu and the single mobile menu so the two never drift apart.
    State-gated (mirrors the guard each action method enforces on its own):
      trashed  → Restore, Force Delete
      pending  → Stop Deletion, Instant Purge
      active   → Schedule Deletion, Soft Delete
    then a divider, then the scaffolded neutral actions (email / verification —
    wiring points only, not yet functional) for any non-trashed app user.

    Expects: $record, $state, $isApp, $unverified
--}}
@if ($state === 'trashed')
    @can('users.restore')
        <x-admin.dropdown-item @click="$wire.confirmRestore({{ $record->id }})">
            <x-lucide-rotate-ccw class="size-4" />
            Restore
        </x-admin.dropdown-item>
    @endcan

    @can('users.force-delete')
        <x-admin.dropdown-item variant="destructive" @click="$wire.confirmForceDelete({{ $record->id }})">
            <x-lucide-trash-2 class="size-4" />
            Force Delete
        </x-admin.dropdown-item>
    @endcan
@elseif ($isApp)
    @if ($state === 'pending')
        @can('users.delete')
            <x-admin.dropdown-item @click="$wire.stopDeletion({{ $record->id }})">
                <x-lucide-shield-check class="size-4" />
                Stop Deletion
            </x-admin.dropdown-item>
        @endcan

        @can('users.force-delete')
            <x-admin.dropdown-item variant="destructive" @click="$wire.confirmInstantPurge({{ $record->id }})">
                <x-lucide-trash-2 class="size-4" />
                Instant Purge
            </x-admin.dropdown-item>
        @endcan
    @else
        @can('users.delete')
            <x-admin.dropdown-item @click="$wire.openScheduleDeletionDialog({{ $record->id }})">
                <x-lucide-clock class="size-4" />
                Schedule Deletion
            </x-admin.dropdown-item>

            <x-admin.dropdown-item variant="destructive" @click="$wire.confirmDelete({{ $record->id }})">
                <x-lucide-trash class="size-4" />
                Soft Delete
            </x-admin.dropdown-item>
        @endcan
    @endif

    <x-admin.dropdown-separator />

    @if ($unverified)
        <x-admin.dropdown-item class="text-muted-foreground" @click="$wire.verifyEmailManually">
            <x-lucide-badge-check class="size-4" />
            Verify Email
        </x-admin.dropdown-item>

        <x-admin.dropdown-item class="text-muted-foreground" @click="$wire.resendVerificationEmail">
            <x-lucide-mail class="size-4" />
            Resend Verification
        </x-admin.dropdown-item>
    @endif

    <x-admin.dropdown-item class="text-muted-foreground" @click="$wire.sendPasswordResetLink">
        <x-lucide-key class="size-4" />
        Send Password Reset
    </x-admin.dropdown-item>
@endif
