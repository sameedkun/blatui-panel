@php
    /** @var \App\Models\User $record */
    $state = $record->lifecycleState();
    $banned = (bool) $record->banned_at;
    $unverified = ! $record->email_verified_at;
    $isApp = $record->isAppUser();
@endphp

<x-ui.card>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

        {{-- Identity --}}
        <div class="flex items-center gap-4">
            <x-ui.avatar class="size-16 shrink-0 rounded-full">
                @if ($record->avatar)
                    <x-ui.avatar-image :src="$record->avatar" :alt="$record->name" />
                @endif
                <x-ui.avatar-fallback class="text-lg">
                    {{ strtoupper(substr($record->name, 0, 2)) }}
                </x-ui.avatar-fallback>
            </x-ui.avatar>

            <div class="min-w-0">
                <p class="truncate text-lg font-semibold">{{ $record->name }}</p>
                <p class="truncate text-sm text-muted-foreground">{{ $record->email }}</p>

                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <x-ui.badge
                        variant="secondary">{{ \Illuminate\Support\Str::headline($record->type->value) }}</x-ui.badge>

                    @if ($state === 'trashed')
                        <x-ui.badge variant="destructive">
                            <x-lucide-trash-2 class="size-3" />
                            Deleted
                        </x-ui.badge>
                    @endif

                    @if ($banned)
                        <x-ui.badge variant="destructive">Banned</x-ui.badge>
                    @endif

                    @if ($record->email_verified_at)
                        <x-ui.badge variant="default"
                            class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                            <x-lucide-badge-check class="size-3" />
                            Verified
                        </x-ui.badge>
                    @else
                        <x-ui.badge variant="outline">Unverified</x-ui.badge>
                    @endif

                    @if ($state === 'pending')
                        <x-ui.badge variant="outline"
                            class="gap-1 border-amber-500/40 text-amber-700 dark:text-amber-400">
                            <x-lucide-clock class="size-3" />
                            Purges {{ $record->deletionPurgesAt()?->diffForHumans() }}
                        </x-ui.badge>
                    @endif
                </div>
            </div>
        </div>

        {{-- Actions: inline on desktop, collapsed into a menu on mobile. State-gated —
             each button is also rejected by its action method if invoked out of state. --}}
        <div class="shrink-0">

            {{-- Desktop --}}
            <div class="hidden flex-wrap items-center gap-2 md:flex">
                @if ($state === 'trashed')
                    @can('users.restore')
                        <x-ui.button variant="outline" wire:click="confirmRestore({{ $record->id }})">
                            <x-lucide-rotate-ccw class="size-4" />
                            Restore
                        </x-ui.button>
                    @endcan

                    @can('users.force-delete')
                        <x-ui.button variant="destructive" wire:click="confirmForceDelete({{ $record->id }})">
                            <x-lucide-trash-2 class="size-4" />
                            Force Delete
                        </x-ui.button>
                    @endcan
                @else
                    @if ($state === 'active')
                        @can('users.edit')
                            <x-ui.button variant="outline" href="{{ route('admin.users.edit', $record) }}">
                                <x-lucide-pencil class="size-4" />
                                Edit
                            </x-ui.button>
                        @endcan
                    @endif

                    @if ($banned)
                        @can('users.unban')
                            <x-ui.button variant="outline" wire:click="unban({{ $record->id }})">
                                <x-lucide-shield-check class="size-4" />
                                Unban
                            </x-ui.button>
                        @endcan
                    @else
                        @can('users.ban')
                            <x-ui.button variant="outline" wire:click="openBanDialog({{ $record->id }})">
                                <x-lucide-ban class="size-4" />
                                Ban
                            </x-ui.button>
                        @endcan
                    @endif

                    @if ($isApp)
                        @if ($state === 'pending')
                            @can('users.delete')
                                <x-ui.button variant="outline" wire:click="stopDeletion({{ $record->id }})">
                                    <x-lucide-shield-check class="size-4" />
                                    Stop Deletion
                                </x-ui.button>
                            @endcan

                            @can('users.force-delete')
                                <x-ui.button variant="ghost" class="text-destructive hover:text-destructive"
                                    wire:click="confirmInstantPurge({{ $record->id }})">
                                    <x-lucide-trash-2 class="size-4" />
                                    Instant Purge
                                </x-ui.button>
                            @endcan
                        @else
                            @can('users.delete')
                                <x-ui.button variant="outline" wire:click="openScheduleDeletionDialog({{ $record->id }})">
                                    <x-lucide-clock class="size-4" />
                                    Schedule Deletion
                                </x-ui.button>

                                <x-ui.button variant="ghost" class="text-destructive hover:text-destructive"
                                    wire:click="confirmDelete({{ $record->id }})">
                                    <x-lucide-trash class="size-4" />
                                    Soft Delete
                                </x-ui.button>
                            @endcan
                        @endif

                        {{-- Scaffolded — wiring points only, not yet functional. --}}
                        <div class="mx-1 h-6 w-px bg-border"></div>

                        @if ($unverified)
                            <x-admin.tooltip text="Not yet available">
                                <x-ui.button variant="outline" class="text-muted-foreground"
                                    wire:click="verifyEmailManually">
                                    <x-lucide-badge-check class="size-4" />
                                    Verify Email
                                </x-ui.button>
                            </x-admin.tooltip>

                            <x-admin.tooltip text="Not yet available">
                                <x-ui.button variant="outline" class="text-muted-foreground"
                                    wire:click="resendVerificationEmail">
                                    <x-lucide-mail class="size-4" />
                                    Resend Verification
                                </x-ui.button>
                            </x-admin.tooltip>
                        @endif

                        <x-admin.tooltip text="Not yet available">
                            <x-ui.button variant="outline" class="text-muted-foreground"
                                wire:click="sendPasswordResetLink">
                                <x-lucide-key class="size-4" />
                                Send Password Reset
                            </x-ui.button>
                        </x-admin.tooltip>
                    @endif
                @endif
            </div>

            {{-- Mobile --}}
            <div class="md:hidden">
                <x-admin.dropdown align="end" width="w-56">
                    <x-slot:trigger>
                        <x-ui.button variant="outline" size="sm">
                            Actions
                            <x-lucide-chevron-down class="size-4" />
                        </x-ui.button>
                    </x-slot:trigger>

                    @if ($state === 'trashed')
                        @can('users.restore')
                            <x-admin.dropdown-item @click="$wire.confirmRestore({{ $record->id }})">
                                <x-lucide-rotate-ccw class="size-4" />
                                Restore
                            </x-admin.dropdown-item>
                        @endcan

                        @can('users.force-delete')
                            <x-admin.dropdown-item variant="destructive"
                                @click="$wire.confirmForceDelete({{ $record->id }})">
                                <x-lucide-trash-2 class="size-4" />
                                Force Delete
                            </x-admin.dropdown-item>
                        @endcan
                    @else
                        @if ($state === 'active')
                            @can('users.edit')
                                <x-admin.dropdown-item href="{{ route('admin.users.edit', $record) }}">
                                    <x-lucide-pencil class="size-4" />
                                    Edit
                                </x-admin.dropdown-item>
                            @endcan
                        @endif

                        @if ($banned)
                            @can('users.unban')
                                <x-admin.dropdown-item @click="$wire.unban({{ $record->id }})">
                                    <x-lucide-shield-check class="size-4" />
                                    Unban
                                </x-admin.dropdown-item>
                            @endcan
                        @else
                            @can('users.ban')
                                <x-admin.dropdown-item @click="$wire.openBanDialog({{ $record->id }})">
                                    <x-lucide-ban class="size-4" />
                                    Ban
                                </x-admin.dropdown-item>
                            @endcan
                        @endif

                        @if ($isApp)
                            @if ($state === 'pending')
                                @can('users.delete')
                                    <x-admin.dropdown-item @click="$wire.stopDeletion({{ $record->id }})">
                                        <x-lucide-shield-check class="size-4" />
                                        Stop Deletion
                                    </x-admin.dropdown-item>
                                @endcan

                                @can('users.force-delete')
                                    <x-admin.dropdown-separator />
                                    <x-admin.dropdown-item variant="destructive"
                                        @click="$wire.confirmInstantPurge({{ $record->id }})">
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

                                    <x-admin.dropdown-separator />
                                    <x-admin.dropdown-item variant="destructive"
                                        @click="$wire.confirmDelete({{ $record->id }})">
                                        <x-lucide-trash class="size-4" />
                                        Soft Delete
                                    </x-admin.dropdown-item>
                                @endcan
                            @endif

                            <x-admin.dropdown-separator />

                            @if ($unverified)
                                <x-admin.dropdown-item class="text-muted-foreground"
                                    @click="$wire.verifyEmailManually">
                                    <x-lucide-badge-check class="size-4" />
                                    Verify Email
                                </x-admin.dropdown-item>

                                <x-admin.dropdown-item class="text-muted-foreground"
                                    @click="$wire.resendVerificationEmail">
                                    <x-lucide-mail class="size-4" />
                                    Resend Verification
                                </x-admin.dropdown-item>
                            @endif

                            <x-admin.dropdown-item class="text-muted-foreground"
                                @click="$wire.sendPasswordResetLink">
                                <x-lucide-key class="size-4" />
                                Send Password Reset
                            </x-admin.dropdown-item>
                        @endif
                    @endif
                </x-admin.dropdown>
            </div>

        </div>
    </div>
</x-ui.card>
