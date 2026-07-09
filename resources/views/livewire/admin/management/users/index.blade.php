<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header title="Users" description="Manage all registered accounts." :breadcrumbs="[['label' => 'Home', 'url' => route('admin.dashboard')], ['label' => 'Users']]">
        @can('users.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.users.create') }}">
                    <x-lucide-plus class="size-4" />
                    Create User
                </x-ui.button>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    {{-- Stats --}}
    @if (count($stats))
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($stats as $stat)
                <x-admin.stat-card :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :description="$stat['description']" />
            @endforeach
        </div>
    @endif

    {{-- Status tabs --}}
    <div class="flex items-center gap-1 border-b border-border">
        @foreach (['active' => 'Active', 'pending' => 'Pending Deletion', 'trashed' => 'Deleted'] as $tabKey => $tabLabel)
            <button wire:click="setTab('{{ $tabKey }}')" type="button"
                class="relative -mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors {{ $tab === $tabKey ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground' }}">
                {{ $tabLabel }}
            </button>
        @endforeach
    </div>

    {{-- Toolbar --}}
    <x-admin.filter-bar :config="$filterBarConfig" :filters="$filters" :has-active-filters="$this->hasActiveFilters()"
        search-placeholder="Search name, email, ID..." />

    {{-- Table --}}
    @php
        $canBulkAct = match ($tab) {
            'trashed' => auth()->user()->canAny(['users.restore', 'users.force-delete']),
            'pending' => auth()->user()->canAny(['users.delete', 'users.force-delete']),
            default => auth()->user()->canAny(['users.ban', 'users.unban', 'users.delete']),
        };
        $canRowAct = match ($tab) {
            'trashed' => auth()->user()->canAny(['users.edit', 'users.restore', 'users.force-delete']),
            'pending' => auth()->user()->canAny(['users.edit', 'users.delete', 'users.force-delete']),
            default => auth()->user()->canAny(['users.edit', 'users.ban', 'users.delete']),
        };
    @endphp
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="w-10 px-4 py-3 text-left">
                        @if ($canBulkAct)
                            <input type="checkbox" x-data
                                :checked="@js($pageIds).length > 0 && @js($pageIds).every(id => $wire
                                    .selectedIds.includes(id))"
                                @change="$wire.toggleSelectPage(@js($pageIds))"
                                class="blat-checkbox cursor-pointer dark:bg-input/30" />
                        @endif
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('name')" class="flex items-center gap-1 font-medium text-foreground">
                            User
                            @if ($sortBy === 'name')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Status</th>
                    <th class="hidden px-4 py-3 text-left md:table-cell">
                        <button wire:click="sort('registration_date')"
                            class="flex items-center gap-1 font-medium text-foreground">
                            Registered
                            @if ($sortBy === 'registration_date')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground xl:table-cell">Last Login</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($users as $user)
                    <tr wire:key="user-row-{{ $user->id }}"
                        class="group hover:bg-muted/30 {{ in_array((string) $user->id, $this->selectedIds) ? 'bg-muted/20' : '' }}">

                        {{-- Checkbox --}}
                        <td class="px-4 py-3">
                            @if ($canBulkAct)
                                <input type="checkbox" x-data :checked="$wire.selectedIds.includes('{{ $user->id }}')"
                                    @change="$wire.toggleSelection('{{ $user->id }}')"
                                    class="blat-checkbox cursor-pointer dark:bg-input/30" />
                            @endif
                        </td>

                        {{-- User cell: avatar + name + email + ext id --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-ui.avatar class="size-8 shrink-0 rounded-full">
                                    @if ($user->avatar)
                                        <x-ui.avatar-image :src="$user->avatar" :alt="$user->name" />
                                    @endif
                                    <x-ui.avatar-fallback class="text-xs">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </x-ui.avatar-fallback>
                                </x-ui.avatar>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate font-medium">{{ $user->name }}</span>
                                        {{-- Social icons --}}
                                        @if ($user->google_id)
                                            <x-icons.google class="size-4 text-red-500" title="Google" />
                                        @endif

                                        @if ($user->apple_id)
                                            <x-icons.apple class="size-4 text-foreground" title="Apple" />
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-xs text-muted-foreground">{{ $user->email }}</span>
                                        {{-- External ID on hover --}}
                                        <x-admin.tooltip :text="$user->external_id" tipClass="font-mono">
                                            <span
                                                class="hidden cursor-default font-mono text-[10px] text-muted-foreground/50 group-hover:inline">
                                                {{ substr($user->external_id, 0, 8) }}
                                            </span>
                                        </x-admin.tooltip>
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if ($user->isPendingDeletion())
                                {{-- Pending-deletion countdown only (grace period) --}}
                                <div class="inline-flex"
                                    x-data="{
                                        purgeAt: {{ $user->deletionPurgesAt()->getTimestamp() * 1000 }},
                                        now: Date.now(),
                                        get remaining() {
                                            const mins = Math.max(0, Math.floor((this.purgeAt - this.now) / 60000));
                                            const h = Math.floor(mins / 60);
                                            return h > 0 ? `Deleting in ${h}h` : (mins > 0 ? `Deleting in ${mins}m` : 'Purging…');
                                        },
                                    }"
                                    x-init="setInterval(() => now = Date.now(), 60000)">
                                    <x-ui.badge variant="destructive" class="gap-1 bg-amber-500/15 text-amber-700 dark:text-amber-400 border-0">
                                        <x-lucide-clock class="size-3" />
                                        <span x-text="remaining"></span>
                                    </x-ui.badge>
                                </div>
                            @elseif ($user->banned_at)
                                <x-ui.badge variant="destructive">Banned</x-ui.badge>
                            @elseif(!$user->email_verified_at)
                                <x-ui.badge variant="secondary">Unverified</x-ui.badge>
                            @else
                                <x-ui.badge variant="default"
                                    class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-0">Active</x-ui.badge>
                            @endif
                        </td>

                        {{-- Registered --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground md:table-cell">
                            {{ $user->registration_date?->format('M d, Y') ?? '-' }}
                        </td>

                        {{-- Last login --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground xl:table-cell">
                            {{ $user->last_login?->diffForHumans() ?? 'Never' }}
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('users.view')
                                    <x-admin.tooltip text="View profile">
                                        <x-ui.button variant="ghost" size="icon" class="size-8"
                                            href="{{ route('admin.users.show', $user) }}">
                                            <x-lucide-eye class="size-4" />
                                            <span class="sr-only">View profile</span>
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endcan

                                @if ($canRowAct)
                                    <x-admin.dropdown align="end" width="w-48">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">Actions</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('users.edit')
                                        <x-admin.dropdown-item href="{{ route('admin.users.edit', $user) }}">
                                            <x-lucide-pencil class="size-4" />
                                            Edit
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @if ($tab === 'trashed')
                                        @can('users.restore')
                                            <x-admin.dropdown-item @click="$wire.confirmRestore({{ $user->id }})">
                                                <x-lucide-rotate-ccw class="size-4" />
                                                Restore
                                            </x-admin.dropdown-item>
                                        @endcan

                                        @can('users.force-delete')
                                            <x-admin.dropdown-separator />
                                            <x-admin.dropdown-item variant="destructive"
                                                @click="$wire.confirmForceDelete({{ $user->id }})">
                                                <x-lucide-trash-2 class="size-4" />
                                                Permanently Delete
                                            </x-admin.dropdown-item>
                                        @endcan
                                    @elseif ($tab === 'pending')
                                        @can('users.delete')
                                            <x-admin.dropdown-item @click="$wire.stopDeletion({{ $user->id }})">
                                                <x-lucide-shield-check class="size-4" />
                                                Stop Deletion
                                            </x-admin.dropdown-item>
                                        @endcan

                                        @can('users.force-delete')
                                            <x-admin.dropdown-separator />
                                            <x-admin.dropdown-item variant="destructive"
                                                @click="$wire.confirmInstantPurge({{ $user->id }})">
                                                <x-lucide-trash-2 class="size-4" />
                                                Purge Now
                                            </x-admin.dropdown-item>
                                        @endcan
                                    @else
                                        @can('users.ban')
                                            @if (!$user->banned_at)
                                                <x-admin.dropdown-item @click="$wire.openBanDialog({{ $user->id }})">
                                                    <x-lucide-ban class="size-4" />
                                                    Ban
                                                </x-admin.dropdown-item>
                                            @else
                                                <x-admin.dropdown-item @click="$wire.unban({{ $user->id }})">
                                                    <x-lucide-shield-check class="size-4" />
                                                    Unban
                                                </x-admin.dropdown-item>
                                            @endif
                                        @endcan

                                        @can('users.delete')
                                            <x-admin.dropdown-item @click="$wire.openScheduleDeletionDialog({{ $user->id }})">
                                                <x-lucide-clock class="size-4" />
                                                Schedule Deletion
                                            </x-admin.dropdown-item>

                                            <x-admin.dropdown-separator />
                                            <x-admin.dropdown-item variant="destructive"
                                                @click="$wire.confirmDelete({{ $user->id }})">
                                                <x-lucide-trash class="size-4" />
                                                Delete
                                            </x-admin.dropdown-item>
                                        @endcan
                                    @endif
                                </x-admin.dropdown>
                                @endif
                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-users class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">No users found.</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">Clear filters</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$users" />

    {{--  Bulk action bar (fixed bottom, shows when rows selected) --}}
    @if (count($selectedIds) > 0)
        <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background px-3 py-2 shadow-xl"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">
            <x-admin.tooltip text="Clear selection">
                <x-ui.button variant="ghost" size="icon" class="size-8 rounded-full" wire:click="clearSelection">
                    <x-lucide-x class="size-4" />
                </x-ui.button>
            </x-admin.tooltip>

            <div class="mx-1 h-4 w-px bg-border"></div>
            <span class="px-1 text-sm font-medium">{{ count($selectedIds) }} selected</span>
            <div class="mx-1 h-4 w-px bg-border"></div>

            @foreach ($this->availableBulkActions as $action)
                @can($action['permission'])
                    <x-admin.tooltip :text="$action['label']">
                        <x-ui.button variant="{{ $action['variant'] ?? 'ghost' }}" size="icon"
                            class="size-8 rounded-full"
                            wire:click="{{ $action['confirm'] ? 'openBulkConfirm(\'' . $action['key'] . '\')' : 'executeBulk' . str_replace('-', '', ucwords($action['key'], '-')) . '()' }}">
                            <x-dynamic-component :component="'lucide-' . $action['icon']" class="size-4" />
                        </x-ui.button>
                    </x-admin.tooltip>
                @endcan
            @endforeach
        </div>
    @endif

    {{-- ── Confirmation dialogs ──────────────────────────────────────────── --}}
    @include('livewire.admin.management.users.partials.dialogs')

</div>
