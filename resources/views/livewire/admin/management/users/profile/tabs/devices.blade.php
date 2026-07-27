@php
    /** @var \App\Models\User $record */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $deviceHistory */
@endphp

<div class="flex flex-col gap-6">

    <x-ui.card class="p-0 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/50 p-4">
            <div>
                <h3 class="text-sm font-semibold text-foreground">Devices</h3>
                <p class="text-xs text-muted-foreground">Every device {{ $record->name }} has logged in from.</p>
            </div>

            @can('devices.revoke')
                @if ($this->activeDeviceCount() > 0)
                    <x-ui.button variant="outline" size="sm" wire:click="confirmRevokeAllDevices"
                        class="gap-1.5 text-xs shadow-2xs">
                        <x-lucide-shield-off class="size-3.5" />
                        Revoke All
                    </x-ui.button>
                @endif
            @endcan
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                        <th class="px-4 py-3 text-left font-semibold">Device</th>
                        <th class="hidden px-4 py-3 text-left font-semibold md:table-cell">Platform</th>
                        <th class="hidden px-4 py-3 text-left font-semibold lg:table-cell">Location / IP</th>
                        <th class="px-4 py-3 text-left font-semibold">Last Seen</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        @canany(['devices.block', 'devices.unblock', 'devices.revoke'])
                            <th class="w-10 px-4 py-3"></th>
                        @endcanany
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @forelse ($deviceHistory as $device)
                        <tr wire:key="user-device-{{ $device->id }}" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3.5">
                                <p class="font-semibold text-foreground">{{ $device->displayName() }}</p>
                                <p class="text-xs text-muted-foreground">{{ $device->device_type?->label() ?? 'Unknown type' }}</p>
                            </td>

                            <td class="hidden px-4 py-3.5 text-muted-foreground md:table-cell">
                                {{ $device->platform ?? '—' }}
                                @if ($device->os)
                                    <span class="text-xs">/ {{ $device->os }}</span>
                                @endif
                            </td>

                            <td class="hidden px-4 py-3.5 text-muted-foreground lg:table-cell">
                                {{ $device->city || $device->country ? trim(($device->city ?? '').(($device->city && $device->country) ? ', ' : '').($device->country ?? '')) : '—' }}
                                @if ($device->ip_address && auth()->user()->can('devices.investigate'))
                                    <p class="text-xs">{{ $device->ip_address }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3.5 text-muted-foreground">
                                <x-admin.tooltip :text="$device->last_seen_at?->toDayDateTimeString() ?? 'Never seen'">
                                    <span>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</span>
                                </x-admin.tooltip>
                            </td>

                            <td class="px-4 py-3.5">
                                <x-admin.device-status-badge :device="$device" />
                            </td>

                            @canany(['devices.block', 'devices.unblock', 'devices.revoke'])
                                <td class="px-4 py-3.5 text-right">
                                    <x-admin.dropdown align="end" width="w-48">
                                        <x-slot:trigger>
                                            <x-ui.button variant="ghost" size="icon" class="size-8">
                                                <x-lucide-ellipsis class="size-4" />
                                                <span class="sr-only">Actions</span>
                                            </x-ui.button>
                                        </x-slot:trigger>

                                        @if ($device->is_active)
                                            @can('devices.revoke')
                                                <x-admin.dropdown-item @click="$wire.confirmRevokeDevice('{{ $device->ulid }}')">
                                                    <x-lucide-shield-off class="size-4" />
                                                    Revoke
                                                </x-admin.dropdown-item>
                                            @endcan
                                        @endif

                                        @if ($device->is_blocked)
                                            @can('devices.unblock')
                                                <x-admin.dropdown-item @click="$wire.unblockDevice('{{ $device->ulid }}')">
                                                    <x-lucide-shield-check class="size-4" />
                                                    Unblock
                                                </x-admin.dropdown-item>
                                            @endcan
                                        @else
                                            @can('devices.block')
                                                <x-admin.dropdown-item variant="destructive" @click="$wire.openBlockDeviceDialog('{{ $device->ulid }}')">
                                                    <x-lucide-shield-ban class="size-4" />
                                                    Block
                                                </x-admin.dropdown-item>
                                            @endcan
                                        @endif
                                    </x-admin.dropdown>
                                </td>
                            @endcanany
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <x-lucide-smartphone class="size-8 text-muted-foreground/30" />
                                    <p class="text-sm font-medium">No devices.</p>
                                    <p class="text-xs">{{ $record->name }} hasn't logged in from any device yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($deviceHistory->hasPages())
            <div class="border-t border-border p-4">
                {{ $deviceHistory->links('livewire.admin.partials.pagination') }}
            </div>
        @endif
    </x-ui.card>

</div>
