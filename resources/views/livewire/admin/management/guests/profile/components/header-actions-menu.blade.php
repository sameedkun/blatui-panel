{{--
    Secondary action items for the profile header's "Actions" menu — shared by
    the desktop menu and the single mobile menu so the two never drift apart.
    State-gated (mirrors the guard each action method enforces on its own):
      trashed  → Restore, Force Delete (legacy safety net; the active-state
                 Delete below is instant, so guests shouldn't normally reach
                 this state anymore)
      active   → Convert to App User, Merge into Existing Account (both
                 hidden while banned — a banned guest must be unbanned
                 first), then a divider, then Delete (destructive,
                 permanent — no grace period)

    These are deliberately separate, explicit operations — the admin picks
    one, nothing auto-detects a "collision" and merges silently.

    Expects: $record, $state
--}}
@if ($state === 'trashed')
    @can('guests.restore')
        <x-admin.dropdown-item @click="$wire.confirmRestore({{ $record->id }})">
            <x-lucide-rotate-ccw class="size-4" />
            {{ __('common.restore') }}
        </x-admin.dropdown-item>
    @endcan

    @can('guests.force-delete')
        <x-admin.dropdown-item variant="destructive" @click="$wire.confirmForceDelete({{ $record->id }})">
            <x-lucide-trash-2 class="size-4" />
            {{ __('common.force_delete') }}
        </x-admin.dropdown-item>
    @endcan
@else
    @if ($record->isGuest() && ! $record->banned_at)
        @can('users.convert')
            <x-admin.dropdown-item @click="$wire.openConvertDialog({{ $record->id }})">
                <x-lucide-user-plus class="size-4" />
                {{ __('guests.actions.convert') }}
            </x-admin.dropdown-item>
        @endcan

        @can('users.merge')
            <x-admin.dropdown-item @click="$wire.openMergeDialog({{ $record->id }})">
                <x-lucide-merge class="size-4" />
                {{ __('guests.actions.merge') }}
            </x-admin.dropdown-item>
        @endcan
    @endif

    @can('guests.delete')
        <x-admin.dropdown-separator />

        <x-admin.dropdown-item variant="destructive" @click="$wire.confirmDelete({{ $record->id }})">
            <x-lucide-trash class="size-4" />
            {{ __('common.delete') }}
        </x-admin.dropdown-item>
    @endcan
@endif
