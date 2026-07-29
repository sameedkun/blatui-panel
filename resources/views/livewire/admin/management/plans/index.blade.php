<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('plans.title')" :description="__('plans.subtitle')" :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('plans.title')]]">
        @can('plans.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.plans.create') }}">
                    <x-lucide-plus class="size-4" />
                    {{ __('plans.actions.create') }}
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

    {{-- Toolbar --}}
    <x-admin.filter-bar :config="$filterBarConfig" :filters="$filters" :has-active-filters="$this->hasActiveFilters()"
        :search-placeholder="__('plans.placeholders.search')" />

    {{-- Table --}}
    @php
        $canBulkAct = auth()->user()->canAny(['plans.edit', 'plans.delete']);
        $canRowAct = auth()->user()->canAny(['plans.edit', 'plans.delete']);
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
                            {{ __('plans.singular') }}
                            @if ($sortBy === 'name')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('plans.fields.status') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">{{ __('plans.fields.starting_price') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">{{ __('plans.fields.subscribers') }}</th>
                    <th class="hidden px-4 py-3 text-left xl:table-cell">
                        <button wire:click="sort('sort_order')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('plans.fields.order') }}
                            @if ($sortBy === 'sort_order')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($plans as $plan)
                    @php $startingPrice = $plan->prices->first(); @endphp
                    <tr wire:key="plan-row-{{ $plan->id }}"
                        class="group hover:bg-muted/30 {{ in_array((string) $plan->id, $this->selectedIds) ? 'bg-muted/20' : '' }}">

                        {{-- Checkbox --}}
                        <td class="px-4 py-3">
                            @if ($canBulkAct)
                                <input type="checkbox" x-data :checked="$wire.selectedIds.includes('{{ $plan->id }}')"
                                    @change="$wire.toggleSelection('{{ $plan->id }}')"
                                    class="blat-checkbox cursor-pointer dark:bg-input/30" />
                            @endif
                        </td>

                        {{-- Plan cell --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                @can('plans.manage')
                                    <a href="{{ route('admin.plans.show', $plan) }}" class="font-medium hover:underline">{{ $plan->name }}</a>
                                @else
                                    <span class="font-medium">{{ $plan->name }}</span>
                                @endcan
                                @if ($plan->is_best_deal)
                                    <x-admin.tooltip :text="__('plans.status.best_deal')">
                                        <x-lucide-star class="size-3.5 fill-amber-400 text-amber-400" />
                                    </x-admin.tooltip>
                                @endif
                            </div>
                            @if ($plan->description)
                                <p class="max-w-xs truncate text-xs text-muted-foreground">{{ $plan->description }}</p>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if ($plan->is_active)
                                <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ __('plans.status.active') }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary">{{ __('plans.status.inactive') }}</x-ui.badge>
                            @endif
                        </td>

                        {{-- Starting price --}}
                        <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">
                            @if ($startingPrice)
                                <span class="font-medium text-foreground">{{ $startingPrice->currency }} {{ number_format((float) $startingPrice->amount, 2) }}</span>
                                <span class="text-xs">/ {{ trans_choice('plans.billing_intervals.'.$startingPrice->billing_interval->value, $startingPrice->billing_period, ['count' => $startingPrice->billing_period]) }}</span>
                                @if ($plan->prices->count() > 1)
                                    <span class="text-xs text-muted-foreground">{{ __('plans.status.more_prices', ['count' => $plan->prices->count() - 1]) }}</span>
                                @endif
                            @else
                                <span class="text-xs">{{ __('plans.status.no_prices') }}</span>
                            @endif
                        </td>

                        {{-- Subscribers --}}
                        <td class="hidden px-4 py-3 lg:table-cell">
                            <x-ui.badge variant="secondary">{{ $plan->subscriptions_count }}</x-ui.badge>
                        </td>

                        {{-- Sort order --}}
                        <td class="hidden px-4 py-3 text-muted-foreground xl:table-cell">{{ $plan->sort_order }}</td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @can('plans.manage')
                                    <x-admin.tooltip :text="__('plans.actions.view')">
                                        <x-ui.button variant="ghost" size="icon" class="size-8"
                                            href="{{ route('admin.plans.show', $plan) }}">
                                            <x-lucide-eye class="size-4" />
                                            <span class="sr-only">{{ __('plans.actions.view') }}</span>
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endcan

                                @if ($canRowAct)
                                <x-admin.dropdown align="end" width="w-48">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">{{ __('plans.actions.actions') }}</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('plans.edit')
                                        <x-admin.dropdown-item href="{{ route('admin.plans.edit', $plan) }}">
                                            <x-lucide-pencil class="size-4" />
                                            {{ __('plans.actions.edit_short') }}
                                        </x-admin.dropdown-item>

                                        <x-admin.dropdown-item @click="$wire.toggleActive({{ $plan->id }})">
                                            @if ($plan->is_active)
                                                <x-lucide-circle-slash class="size-4" />
                                                {{ __('plans.actions.deactivate') }}
                                            @else
                                                <x-lucide-check-circle class="size-4" />
                                                {{ __('plans.actions.activate') }}
                                            @endif
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @can('plans.delete')
                                        <x-admin.dropdown-separator />
                                        <x-admin.dropdown-item variant="destructive"
                                            @click="$wire.confirmDelete({{ $plan->id }})">
                                            <x-lucide-trash class="size-4" />
                                            {{ __('plans.actions.delete') }}
                                        </x-admin.dropdown-item>
                                    @endcan
                                </x-admin.dropdown>
                                @endif
                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-credit-card class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('plans.status.no_plans_found') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">{{ __('plans.actions.clear_filters') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$plans" />

    {{-- Bulk action bar (fixed bottom, shows when rows selected) --}}
    @if (count($selectedIds) > 0)
        <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background px-3 py-2 shadow-xl"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">
            <x-admin.tooltip :text="__('plans.actions.clear_selection')">
                <x-ui.button variant="ghost" size="icon" class="size-8 rounded-full" wire:click="clearSelection">
                    <x-lucide-x class="size-4" />
                </x-ui.button>
            </x-admin.tooltip>

            <div class="mx-1 h-4 w-px bg-border"></div>
            <span class="px-1 text-sm font-medium">{{ __('plans.status.selected', ['count' => count($selectedIds)]) }}</span>
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
    @include('livewire.admin.management.plans.partials.dialogs')

</div>
