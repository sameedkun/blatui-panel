@php
    /** @var \App\Models\User $record */
    $state = $record->lifecycleState();
    $banned = (bool) $record->banned_at;
    $menuData = ['record' => $record, 'state' => $state];
@endphp

<x-ui.card>
    {{-- Identity on the left, actions on the right, same row at every breakpoint —
         the header never stacks. --}}
    <div class="flex items-center justify-between gap-4">

        {{-- Identity --}}
        <div class="flex min-w-0 items-center gap-4">
            <x-ui.avatar class="size-16 shrink-0 rounded-full">
                @if ($record->avatarUrl())
                    <x-ui.avatar-image :src="$record->avatarUrl()" :alt="$record->name" />
                @endif
                <x-ui.avatar-fallback class="text-lg">
                    {{ strtoupper(substr($record->name, 0, 2)) }}
                </x-ui.avatar-fallback>
            </x-ui.avatar>

            <div class="min-w-0">
                <p class="truncate text-lg font-semibold">{{ $record->name }}</p>
                <p class="truncate text-sm text-muted-foreground">{{ $record->email }}</p>

                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <x-ui.badge variant="secondary">Guest</x-ui.badge>

                    @if ($state === 'trashed')
                        <x-ui.badge variant="destructive">
                            <x-lucide-trash-2 class="size-3" />
                            Deleted
                        </x-ui.badge>
                    @endif

                    @if ($banned)
                        <x-ui.badge variant="destructive">Banned</x-ui.badge>
                    @endif
                </div>
            </div>
        </div>

        {{-- Actions: Ban/Unban is the only permanent button; everything else
             lives behind "Actions". State-gating happens here in the view and
             again in each action method. --}}
        <div class="flex shrink-0 items-center gap-2">

            {{-- Primary — desktop only; folded into the single mobile menu below. --}}
            <div class="hidden items-center gap-2 md:flex">
                @if ($state !== 'trashed')
                    @if ($banned)
                        @can('guests.unban')
                            <x-ui.button variant="outline" wire:click="unban({{ $record->id }})">
                                <x-lucide-shield-check class="size-4" />
                                Unban
                            </x-ui.button>
                        @endcan
                    @else
                        @can('guests.ban')
                            <x-ui.button variant="outline" wire:click="openBanDialog({{ $record->id }})">
                                <x-lucide-ban class="size-4" />
                                Ban
                            </x-ui.button>
                        @endcan
                    @endif
                @endif
            </div>

            {{-- Secondary — desktop menu, anchored to its own right/end edge so it
                 always opens inward. --}}
            <div class="hidden md:block">
                <x-admin.dropdown align="end" width="w-64">
                    <x-slot:trigger>
                        <x-ui.button variant="outline">
                            Actions
                            <x-lucide-chevron-down class="size-4" />
                        </x-ui.button>
                    </x-slot:trigger>

                    @include('livewire.admin.management.guests.profile.components.header-actions-menu', $menuData)
                </x-admin.dropdown>
            </div>

            {{-- Mobile — primary + secondary fold into a single menu, still anchored
                 end so it opens inward instead of clipping the left viewport edge. --}}
            <div class="md:hidden">
                <x-admin.dropdown align="end" width="w-64">
                    <x-slot:trigger>
                        <x-ui.button variant="outline">
                            Actions
                            <x-lucide-chevron-down class="size-4" />
                        </x-ui.button>
                    </x-slot:trigger>

                    @if ($state !== 'trashed')
                        @if ($banned)
                            @can('guests.unban')
                                <x-admin.dropdown-item @click="$wire.unban({{ $record->id }})">
                                    <x-lucide-shield-check class="size-4" />
                                    Unban
                                </x-admin.dropdown-item>
                            @endcan
                        @else
                            @can('guests.ban')
                                <x-admin.dropdown-item @click="$wire.openBanDialog({{ $record->id }})">
                                    <x-lucide-ban class="size-4" />
                                    Ban
                                </x-admin.dropdown-item>
                            @endcan
                        @endif
                    @endif

                    <x-admin.dropdown-separator />

                    @include('livewire.admin.management.guests.profile.components.header-actions-menu', $menuData)
                </x-admin.dropdown>
            </div>

        </div>
    </div>
</x-ui.card>
