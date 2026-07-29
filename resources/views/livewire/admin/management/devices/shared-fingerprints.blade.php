<div class="flex flex-col gap-6">

    <x-admin.page-header :title="__('devices.shared.title')" :description="__('devices.shared.description')"
        :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('devices.title'), 'url' => route('admin.devices.index')], ['label' => __('devices.shared.title')]]"
        :back="route('admin.devices.index')" />

    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('devices.fields.fingerprint') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('devices.shared.accounts') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('devices.title') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('devices.shared.shared_by') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($groups as $group)
                    @php $devices = $devicesByFingerprint->get($group->device_fingerprint, collect()); @endphp
                    <tr wire:key="fingerprint-{{ $group->device_fingerprint }}" class="align-top hover:bg-muted/30">
                        <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ substr($group->device_fingerprint, 0, 16) }}…</td>
                        <td class="px-4 py-3">
                            <x-ui.badge variant="secondary">{{ $group->user_count }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge variant="secondary">{{ $group->device_count }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-col gap-1">
                                @foreach ($devices->unique('user_id')->take(5) as $device)
                                    @if ($device->user)
                                        <a href="{{ route('admin.users.show', $device->user) }}" class="text-xs hover:underline" wire:navigate>
                                            {{ $device->user->name }} ({{ $device->user->email }})
                                        </a>
                                    @endif
                                @endforeach
                                @if ($devices->unique('user_id')->count() > 5)
                                    <span class="text-xs text-muted-foreground">{{ __('devices.shared.more', ['count' => $devices->unique('user_id')->count() - 5]) }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-users class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('devices.shared.none_found') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-admin.pagination :paginator="$groups" />

</div>
