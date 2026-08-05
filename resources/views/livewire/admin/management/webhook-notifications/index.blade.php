@php
    use App\Enum\PaymentProvider;
    use Illuminate\Support\Str;
@endphp

<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('webhook_notifications.title')" :description="__('webhook_notifications.subtitle')"
        :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('webhook_notifications.title')]]" />

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
        @foreach ($stats as $stat)
            <x-admin.stat-card :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :description="$stat['description']" />
        @endforeach
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center justify-start gap-2">
        <div class="flex items-center gap-2">
            <label for="webhook-notification-provider" class="text-xs font-medium text-muted-foreground">
                {{ __('webhook_notifications.filters.provider') }}
            </label>
            <select id="webhook-notification-provider" wire:model.live="provider" class="blat-select h-9 text-sm">
                @foreach ($providers as $key => $modelClass)
                    <option value="{{ $key }}">{{ PaymentProvider::from($key)->label() }}</option>
                @endforeach
            </select>
        </div>

        <x-admin.filter-bar :config="$filterBarConfig" :filters="$filters" :has-active-filters="$this->hasActiveFilters()"
            :search-placeholder="__('webhook_notifications.filters.search')" />
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('webhook_notifications.table.notification_type') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">{{ __('webhook_notifications.table.transaction_id') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">{{ __('webhook_notifications.table.product_id') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('webhook_notifications.table.occurred_at') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('webhook_notifications.table.processed') }}</th>
                    <th class="w-16 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($notifications as $notification)
                    @php
                        $typeLabel = method_exists($notification, 'notificationTypeLabel')
                            ? $notification->notificationTypeLabel()
                            : Str::headline($notification->notificationType());
                    @endphp
                    <tr wire:key="webhook-notification-{{ $notification->getKey() }}" class="hover:bg-muted/30 transition-colors">
                        <td class="px-4 py-3">
                            <x-ui.badge variant="secondary">{{ $typeLabel }}</x-ui.badge>
                        </td>
                        <td class="hidden px-4 py-3 font-mono text-xs text-muted-foreground select-all md:table-cell">
                            {{ $notification->transactionId() ?? '—' }}
                        </td>
                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">{{ $notification->productId() ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            @if ($notification->occurredAt())
                                <x-ui.local-time :value="$notification->occurredAt()" format="MMM D, YYYY h:mm A" />
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($notification->isProcessed())
                                <x-ui.badge class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                                    {{ __('webhook_notifications.values.processed') }}
                                </x-ui.badge>
                            @else
                                <x-ui.badge variant="outline">{{ __('webhook_notifications.values.unprocessed') }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('webhook_notifications.manage')
                                @if ($notification instanceof \App\Contracts\RedispatchableNotification)
                                    <x-admin.dropdown align="end" width="w-48">
                                        <x-slot:trigger>
                                            <x-ui.button variant="ghost" size="icon" class="size-8">
                                                <x-lucide-ellipsis class="size-4" />
                                                <span class="sr-only">{{ __('common.actions') }}</span>
                                            </x-ui.button>
                                        </x-slot:trigger>

                                        <x-admin.dropdown-item href="{{ route('admin.webhook-notifications.show', ['provider' => $provider, 'id' => $notification->getKey()]) }}" wire:navigate>
                                            <x-lucide-eye class="size-4" />
                                            {{ __('webhook_notifications.actions.view') }}
                                        </x-admin.dropdown-item>

                                        <x-admin.dropdown-item @click="$wire.confirmRedispatch('{{ $provider }}', {{ $notification->getKey() }})">
                                            <x-lucide-refresh-cw class="size-4" />
                                            {{ $notification->isProcessed() ? __('webhook_notifications.actions.reprocess') : __('webhook_notifications.actions.process') }}
                                        </x-admin.dropdown-item>
                                    </x-admin.dropdown>
                                @else
                                    <x-ui.button variant="ghost" size="sm"
                                        href="{{ route('admin.webhook-notifications.show', ['provider' => $provider, 'id' => $notification->getKey()]) }}"
                                        wire:navigate>
                                        {{ __('webhook_notifications.actions.view') }}
                                    </x-ui.button>
                                @endif
                            @else
                                <x-ui.button variant="ghost" size="sm"
                                    href="{{ route('admin.webhook-notifications.show', ['provider' => $provider, 'id' => $notification->getKey()]) }}"
                                    wire:navigate>
                                    {{ __('webhook_notifications.actions.view') }}
                                </x-ui.button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-webhook class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('webhook_notifications.empty.notifications') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters" class="mt-1 text-xs underline hover:no-underline">{{ __('webhook_notifications.filters.clear') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$notifications" />

    {{-- Confirmation dialog --}}
    <x-admin.confirm-dialog
        id="redispatch-webhook-notification"
        :title="__('webhook_notifications.dialogs.redispatch_title')"
        confirm="$wire.redispatch()"
        cancel="$wire.set('redispatchProvider', null)"
        :confirm-label="__('webhook_notifications.actions.reprocess')"
    >
        {{ __('webhook_notifications.dialogs.redispatch_description') }}
    </x-admin.confirm-dialog>

</div>
