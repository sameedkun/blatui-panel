<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header title="Blocked IPs" description="Block an IP globally or for a single user, with optional expiry and hit tracking."
        :breadcrumbs="[['label' => 'Home', 'url' => route('admin.dashboard')], ['label' => 'Blocked IPs']]">
        @can('blocked-ips.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.blocked-ips.create') }}" wire:navigate>
                    <x-lucide-plus class="size-4" />
                    Block IP
                </x-ui.button>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <x-admin.stat-card :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :description="$stat['description']" />
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <x-admin.filter-bar :config="$filterBarConfig" :filters="$filters" :has-active-filters="$this->hasActiveFilters()"
            search-placeholder="Search IP address..." />

        @can('blocked-ips.delete')
            @if ($this->expiredCount() > 0)
                <x-ui.button variant="outline" size="sm" wire:click="confirmDeleteAllExpired">
                    <x-lucide-trash class="size-3.5" />
                    Delete {{ $this->expiredCount() }} Expired
                </x-ui.button>
            @endif
        @endcan
    </div>

    {{-- Table --}}
    @php
        $canRowAct = auth()->user()->canAny(['blocked-ips.update', 'blocked-ips.delete', 'devices.view']);
        $canBulkAct = auth()->user()->can('blocked-ips.delete');
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
                    <th class="px-4 py-3 text-left font-medium text-foreground">IP Address</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Scope</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">Reason</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">Created By</th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('hits')" class="flex items-center gap-1 font-medium text-foreground">
                            Hits
                            @if ($sortBy === 'hits')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">Last Hit</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Expires</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($blockedIps as $blockedIp)
                    @php
                        $expiringSoon = $blockedIp->expires_at && $blockedIp->expires_at->isFuture() && $blockedIp->expires_at->diffInHours(now()) < 24;
                        $expired = $blockedIp->expires_at && $blockedIp->expires_at->isPast();
                    @endphp
                    <tr wire:key="blocked-ip-row-{{ $blockedIp->id }}"
                        class="group hover:bg-muted/30 {{ in_array((string) $blockedIp->id, $this->selectedIds) ? 'bg-muted/20' : '' }}">

                        <td class="px-4 py-3">
                            @if ($canBulkAct)
                                <input type="checkbox" x-data :checked="$wire.selectedIds.includes('{{ $blockedIp->id }}')"
                                    @change="$wire.toggleSelection('{{ $blockedIp->id }}')"
                                    class="blat-checkbox cursor-pointer dark:bg-input/30" />
                            @endif
                        </td>

                        <td class="px-4 py-3 font-mono">{{ $blockedIp->ip_address }}</td>

                        <td class="px-4 py-3">
                            @if ($blockedIp->user_id)
                                @can('users.manage')
                                    <a href="{{ route('admin.users.show', $blockedIp->user_id) }}" class="text-xs hover:underline" wire:navigate>
                                        {{ $blockedIp->user?->email ?? 'User #'.$blockedIp->user_id }}
                                    </a>
                                @else
                                    <span class="text-xs">{{ $blockedIp->user?->email ?? 'User #'.$blockedIp->user_id }}</span>
                                @endcan
                            @else
                                <x-ui.badge variant="destructive">Global</x-ui.badge>
                            @endif
                        </td>

                        <td class="hidden max-w-xs truncate px-4 py-3 text-muted-foreground md:table-cell" title="{{ $blockedIp->reason }}">
                            {{ $blockedIp->reason ?: '—' }}
                        </td>

                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">{{ $blockedIp->blockedBy?->name ?? 'System' }}</td>

                        <td class="px-4 py-3">
                            <x-ui.badge variant="secondary">{{ number_format($blockedIp->hits) }}</x-ui.badge>
                        </td>

                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                            {{ $blockedIp->last_hit_at?->diffForHumans() ?? 'Never' }}
                        </td>

                        <td class="px-4 py-3">
                            @if (! $blockedIp->expires_at)
                                <x-ui.badge variant="outline">Permanent</x-ui.badge>
                            @elseif ($expired)
                                <x-ui.badge variant="secondary">Expired</x-ui.badge>
                            @else
                                <span class="{{ $expiringSoon ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground' }}">
                                    {{ $blockedIp->expires_at->diffForHumans() }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-right">
                            @if ($canRowAct)
                                <x-admin.dropdown align="end" width="w-52">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">Actions</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('devices.view')
                                        <x-admin.dropdown-item @click="$wire.openIpActivityPanel('{{ $blockedIp->ip_address }}')">
                                            <x-lucide-search class="size-4" />
                                            Who's Behind This IP
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @can('blocked-ips.update')
                                        <x-admin.dropdown-item href="{{ route('admin.blocked-ips.edit', $blockedIp) }}">
                                            <x-lucide-pencil class="size-4" />
                                            Edit
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @can('blocked-ips.delete')
                                        <x-admin.dropdown-separator />
                                        <x-admin.dropdown-item variant="destructive" @click="$wire.confirmDelete({{ $blockedIp->id }})">
                                            <x-lucide-trash class="size-4" />
                                            Delete
                                        </x-admin.dropdown-item>
                                    @endcan
                                </x-admin.dropdown>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-shield-alert class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">No blocked IPs found.</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters" class="mt-1 text-xs underline hover:no-underline">Clear filters</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$blockedIps" />

    {{-- Bulk action bar --}}
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

    {{-- ── Drawers & dialogs ─────────────────────────────────────────────── --}}
    @include('livewire.admin.management.blocked-ips.partials.ip-activity-drawer')
    @include('livewire.admin.management.blocked-ips.partials.confirmations')

</div>
