{{-- "Who is behind this IP" drawer — replaces what a blocked_ips Show page would
     have been. Included by index.blade — shares its Livewire scope. --}}

<x-ui.drawer id="ip-activity" direction="right">
    <x-ui.drawer-content class="sm:max-w-md">
        <x-ui.drawer-header>
            <x-ui.drawer-title class="font-mono">{{ $activityIp }}</x-ui.drawer-title>
            <x-ui.drawer-description>
                {{ trans_choice('blocked_ips.activity.summary', $this->ipActivityDistinctUserCount(), ['count' => $this->ipActivityDistinctUserCount()]) }}
            </x-ui.drawer-description>
        </x-ui.drawer-header>

        <div class="flex flex-col gap-2 overflow-y-auto px-4">
            @forelse ($this->ipActivityDevices() as $device)
                <div wire:key="ip-activity-device-{{ $device->id }}" class="rounded-md border border-border p-3">
                    <div class="flex items-center justify-between gap-2">
                        @if ($device->user)
                            @can('users.manage')
                                <a href="{{ route('admin.users.show', $device->user) }}" class="text-sm font-medium hover:underline" wire:navigate>
                                    {{ $device->user->name }}
                                </a>
                            @else
                                <span class="text-sm font-medium">{{ $device->user->name }}</span>
                            @endcan
                            <p class="text-xs text-muted-foreground">{{ $device->user->email }}</p>
                        @else
                            <span class="text-sm text-muted-foreground">{{ __('blocked_ips.status.unknown_user') }}</span>
                        @endif
                        <x-admin.device-status-badge :device="$device" />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ __('blocked_ips.activity.device_details', [
                            'device' => $device->displayName(),
                            'platform' => $device->platform ?? __('blocked_ips.status.unknown_platform'),
                            'last_seen' => $device->last_seen_at?->diffForHumans() ?? mb_strtolower(__('blocked_ips.status.never')),
                        ]) }}
                    </p>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-muted-foreground">{{ __('blocked_ips.empty.devices') }}</p>
            @endforelse
        </div>

        <x-ui.drawer-footer class="flex-row justify-end">
            <x-ui.button variant="outline" @click="open = false">{{ __('blocked_ips.actions.close') }}</x-ui.button>
        </x-ui.drawer-footer>
    </x-ui.drawer-content>
</x-ui.drawer>
