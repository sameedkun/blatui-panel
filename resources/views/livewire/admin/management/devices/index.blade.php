<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header title="Devices" description="Every device ever registered, across every account."
        :breadcrumbs="[['label' => 'Home', 'url' => route('admin.dashboard')], ['label' => 'Devices']]">
        @can('devices.investigate')
            <x-slot:actions>
                <x-ui.button variant="outline" href="{{ route('admin.devices.shared-fingerprints') }}" wire:navigate>
                    <x-lucide-users class="size-4" />
                    Shared Fingerprints
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
        <x-admin.filter-bar :config="$this->filterBarConfig()" :filters="$filters" :has-active-filters="$this->hasActiveFilters()" />

        <div class="flex items-center gap-2">
            @can('devices.export')
                <x-ui.button variant="outline" size="sm" wire:click="exportCsv">
                    <x-lucide-download class="size-3.5" />
                    Export CSV
                </x-ui.button>
            @endcan
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left font-medium text-foreground">User</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Device</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">Platform</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">App Version</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">Location / IP</th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('last_seen_at')" class="flex items-center gap-1 font-medium text-foreground">
                            Last Seen
                            @if ($sortBy === 'last_seen_at')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Status</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($devices as $device)
                    <tr wire:key="device-row-{{ $device->id }}" class="group hover:bg-muted/30">

                        <td class="px-4 py-3">
                            @if ($device->user)
                                <a href="{{ route('admin.users.show', $device->user) }}" class="font-medium hover:underline" wire:navigate>
                                    {{ $device->user->name }}
                                </a>
                                <p class="text-xs text-muted-foreground">{{ $device->user->email }}</p>
                            @else
                                <span class="text-xs text-muted-foreground">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $device->displayName() }}</p>
                            <p class="text-xs text-muted-foreground">{{ $device->device_type?->label() ?? 'Unknown type' }}</p>
                        </td>

                        <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">
                            {{ $device->platform ?? '—' }}
                            @if ($device->os)
                                <span class="text-xs">/ {{ $device->os }}</span>
                            @endif
                        </td>

                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">{{ $device->app_version ?? '—' }}</td>

                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                            {{ $device->city || $device->country ? trim(($device->city ?? '').(($device->city && $device->country) ? ', ' : '').($device->country ?? '')) : '—' }}
                            @if ($device->ip_address && auth()->user()->can('devices.investigate'))
                                <p class="text-xs">{{ $device->ip_address }}</p>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-muted-foreground">
                            <x-admin.tooltip :text="$device->last_seen_at?->toDayDateTimeString() ?? 'Never seen'">
                                <span>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</span>
                            </x-admin.tooltip>
                        </td>

                        <td class="px-4 py-3">
                            <x-admin.device-status-badge :device="$device" />
                        </td>

                        <td class="px-4 py-3 text-right">
                            @canany(['devices.block', 'devices.unblock', 'devices.revoke'])
                                <x-admin.dropdown align="end" width="w-48">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">Actions</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @if ($device->is_active)
                                        @can('devices.revoke')
                                            <x-admin.dropdown-item @click="$wire.confirmRevoke('{{ $device->ulid }}')">
                                                <x-lucide-shield-off class="size-4" />
                                                Revoke
                                            </x-admin.dropdown-item>
                                        @endcan
                                    @endif

                                    @if ($device->is_blocked)
                                        @can('devices.unblock')
                                            <x-admin.dropdown-item @click="$wire.unblock('{{ $device->ulid }}')">
                                                <x-lucide-shield-check class="size-4" />
                                                Unblock
                                            </x-admin.dropdown-item>
                                        @endcan
                                    @else
                                        @can('devices.block')
                                            <x-admin.dropdown-item variant="destructive" @click="$wire.openBlockDialog('{{ $device->ulid }}')">
                                                <x-lucide-shield-ban class="size-4" />
                                                Block
                                            </x-admin.dropdown-item>
                                        @endcan
                                    @endif
                                </x-admin.dropdown>
                            @endcanany
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-smartphone class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">No devices found.</p>
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
    <x-admin.pagination :paginator="$devices" />

    {{-- ── Dialogs ────────────────────────────────────────────────────────── --}}
    @include('livewire.admin.management.devices.partials.dialogs')

</div>
