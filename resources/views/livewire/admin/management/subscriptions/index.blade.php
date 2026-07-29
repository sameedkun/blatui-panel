@php
    use App\Enum\SubscriptionStatus;

    $liveStatuses = [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::Grace];
    $canManage = auth()->user()->can('subscriptions.manage');
    $canViewUsers = auth()->user()->can('users.manage');
    $canViewPlans = auth()->user()->can('plans.manage');
@endphp

<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('subscriptions.title')" :description="__('subscriptions.subtitle')"
        :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('subscriptions.title')]]" />

    {{-- Stats --}}
    @if (count($stats))
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($stats as $stat)
                <x-admin.stat-card :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :description="$stat['description']" />
            @endforeach
        </div>
    @endif

    {{-- Toolbar --}}
    <x-admin.filter-bar :config="$filterBarConfig" :filters="$filters" :has-active-filters="$this->hasActiveFilters()"
        :search-placeholder="__('subscriptions.placeholders.search')" />

    {{-- Table --}}
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('subscriptions.fields.user') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('subscriptions.fields.plan') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('subscriptions.fields.status') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">{{ __('subscriptions.fields.provider') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">
                        <button wire:click="sort('starts_at')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('subscriptions.fields.started') }}
                            @if ($sortBy === 'starts_at')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">
                        <button wire:click="sort('ends_at')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('subscriptions.fields.ends') }}
                            @if ($sortBy === 'ends_at')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-right font-medium text-foreground">{{ __('subscriptions.fields.paid') }}</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($subscriptions as $subscription)
                    <tr wire:key="subscription-row-{{ $subscription->id }}" class="group hover:bg-muted/30">

                        {{-- User --}}
                        <td class="px-4 py-3">
                            @if ($subscription->user)
                                <div class="min-w-0">
                                    @if ($canViewUsers)
                                        <a href="{{ route('admin.users.show', $subscription->user) }}" class="truncate font-medium hover:underline">
                                            {{ $subscription->user->name }}
                                        </a>
                                    @else
                                        <p class="truncate font-medium">{{ $subscription->user->name }}</p>
                                    @endif
                                    <p class="truncate text-xs text-muted-foreground">{{ $subscription->user->email }}</p>
                                </div>
                            @else
                                <span class="text-muted-foreground">{{ __('subscriptions.status.deleted_user') }}</span>
                            @endif
                        </td>

                        {{-- Plan / price --}}
                        <td class="px-4 py-3">
                            @if ($subscription->plan)
                                @if ($canViewPlans)
                                    <a href="{{ route('admin.plans.show', $subscription->plan) }}" class="font-medium hover:underline">{{ $subscription->plan->name }}</a>
                                @else
                                    <span class="font-medium">{{ $subscription->plan->name }}</span>
                                @endif
                            @else
                                <span class="text-muted-foreground">{{ __('subscriptions.status.deleted_plan') }}</span>
                            @endif
                            @if ($subscription->planPrice)
                                <p class="text-xs text-muted-foreground">
                                    {{ $subscription->planPrice->currency }} {{ number_format((float) $subscription->planPrice->amount, 2) }}
                                    / {{ $subscription->planPrice->billing_interval->label() }}
                                </p>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if (in_array($subscription->status, $liveStatuses, true))
                                <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ $subscription->status->label() }}</x-ui.badge>
                            @elseif ($subscription->status === SubscriptionStatus::Failed)
                                <x-ui.badge variant="destructive">{{ $subscription->status->label() }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary">{{ $subscription->status->label() }}</x-ui.badge>
                            @endif
                        </td>

                        {{-- Provider --}}
                        <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">{{ $subscription->provider->label() }}</td>

                        {{-- Started --}}
                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                            <x-ui.local-time :value="$subscription->starts_at" format="MMM D, YYYY" />
                        </td>

                        {{-- Ends --}}
                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">
                            @if ($subscription->ends_at)
                                <x-ui.local-time :value="$subscription->ends_at" format="MMM D, YYYY" />
                            @else
                                —
                            @endif
                        </td>

                        {{-- Paid --}}
                        <td class="px-4 py-3 text-right">
                            {{ $subscription->amount_paid !== null ? $subscription->currency.' '.number_format((float) $subscription->amount_paid, 2) : '—' }}
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('subscriptions.manage')
                                    <x-admin.tooltip :text="__('subscriptions.view_subscription')">
                                        <x-ui.button variant="ghost" size="icon" class="size-8" href="{{ route('admin.subscriptions.show', $subscription) }}">
                                            <x-lucide-eye class="size-4" />
                                            <span class="sr-only">{{ __('subscriptions.view_subscription') }}</span>
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endcan

                                @if ($canManage && ($this->isLive($subscription) || $this->isReactivatable($subscription)))
                                    <x-admin.dropdown align="end" width="w-56">
                                        <x-slot:trigger>
                                            <x-ui.button variant="ghost" size="icon" class="size-8">
                                                <x-lucide-ellipsis class="size-4" />
                                                <span class="sr-only">{{ __('subscriptions.actions.actions') }}</span>
                                            </x-ui.button>
                                        </x-slot:trigger>

                                        @if ($this->isLive($subscription))
                                            @if ($subscription->is_recurring)
                                                <x-admin.dropdown-item @click="$wire.openCancelAtPeriodEndDialog({{ $subscription->id }})">
                                                    <x-lucide-clock class="size-4" />
                                                    {{ __('subscriptions.actions.cancel_period_end') }}
                                                </x-admin.dropdown-item>
                                            @endif

                                            <x-admin.dropdown-item variant="destructive" @click="$wire.openCancelImmediatelyDialog({{ $subscription->id }})">
                                                <x-lucide-circle-slash class="size-4" />
                                                {{ __('subscriptions.actions.cancel_immediately') }}
                                            </x-admin.dropdown-item>
                                        @elseif ($this->isReactivatable($subscription))
                                            <x-admin.dropdown-item @click="$wire.reactivateRow({{ $subscription->id }})">
                                                <x-lucide-refresh-cw class="size-4" />
                                                {{ __('subscriptions.actions.reactivate') }}
                                            </x-admin.dropdown-item>
                                        @endif
                                    </x-admin.dropdown>
                                @endif
                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-receipt class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('subscriptions.status.none_found') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters" class="mt-1 text-xs underline hover:no-underline">{{ __('subscriptions.actions.clear_filters') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$subscriptions" />

    {{-- ── Action dialogs ────────────────────────────────────────────────── --}}
    @include('livewire.admin.management.subscriptions.partials.dialogs')

</div>
