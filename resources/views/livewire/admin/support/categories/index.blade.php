<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('ticket_categories.title')" :description="__('ticket_categories.subtitle')" :breadcrumbs="[['label' => __('ticket_categories.common.home'), 'url' => route('admin.dashboard')], ['label' => __('ticket_categories.title')]]">
        @can('ticket_categories.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.ticket-categories.create') }}">
                    <x-lucide-plus class="size-4" />
                    {{ __('ticket_categories.actions.create') }}
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
        :search-placeholder="__('ticket_categories.index.search')" />

    {{-- Table --}}
    @php
        $canBulkAct = auth()->user()->canAny(['ticket_categories.edit', 'ticket_categories.delete']);
        $canRowAct = auth()->user()->canAny(['ticket_categories.edit', 'ticket_categories.delete']);
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
                            {{ __('ticket_categories.singular') }}
                            @if ($sortBy === 'name')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('ticket_categories.fields.status') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground sm:table-cell">{{ __('ticket_categories.fields.agents') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">{{ __('ticket_categories.fields.tickets') }}</th>
                    <th class="hidden px-4 py-3 text-left lg:table-cell">
                        <button wire:click="sort('sort_order')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('ticket_categories.fields.sort_order') }}
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
                @forelse ($categories as $category)
                    <tr wire:key="category-row-{{ $category->id }}"
                        class="hover:bg-muted/30 {{ in_array((string) $category->id, $this->selectedIds) ? 'bg-muted/20' : '' }}">

                        {{-- Checkbox --}}
                        <td class="px-4 py-3">
                            @if ($canBulkAct)
                                <input type="checkbox" x-data :checked="$wire.selectedIds.includes('{{ $category->id }}')"
                                    @change="$wire.toggleSelection('{{ $category->id }}')"
                                    class="blat-checkbox cursor-pointer dark:bg-input/30" />
                            @endif
                        </td>

                        {{-- Category --}}
                        <td class="px-4 py-3">
                            @can('ticket_categories.edit')
                                <a href="{{ route('admin.ticket-categories.edit', $category) }}" class="font-medium hover:underline">{{ $category->name }}</a>
                            @else
                                <span class="font-medium">{{ $category->name }}</span>
                            @endcan
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if ($category->is_active)
                                <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ __('ticket_categories.status.active') }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary">{{ __('ticket_categories.status.inactive') }}</x-ui.badge>
                            @endif
                        </td>

                        {{-- Agents --}}
                        <td class="hidden px-4 py-3 sm:table-cell">
                            @if ($category->agents_count > 0)
                                <x-ui.badge variant="secondary">{{ trans_choice('ticket_categories.index.agent_count', $category->agents_count, ['count' => $category->agents_count]) }}</x-ui.badge>
                            @else
                                <span class="text-xs text-muted-foreground italic">{{ __('ticket_categories.index.no_agents') }}</span>
                            @endif
                        </td>

                        {{-- Tickets --}}
                        <td class="hidden px-4 py-3 md:table-cell">
                            <x-ui.badge variant="outline">{{ $category->tickets_count }}</x-ui.badge>
                        </td>

                        {{-- Sort order --}}
                        <td class="hidden px-4 py-3 text-muted-foreground lg:table-cell">{{ $category->sort_order }}</td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            @if ($canRowAct)
                                <x-admin.dropdown align="end" width="w-48">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">{{ __('ticket_categories.common.actions') }}</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('ticket_categories.edit')
                                        <x-admin.dropdown-item href="{{ route('admin.ticket-categories.edit', $category) }}">
                                            <x-lucide-pencil class="size-4" />
                                            {{ __('ticket_categories.actions.edit') }}
                                        </x-admin.dropdown-item>

                                        <x-admin.dropdown-item @click="$wire.toggleActive({{ $category->id }})">
                                            @if ($category->is_active)
                                                <x-lucide-circle-slash class="size-4" />
                                                {{ __('ticket_categories.actions.deactivate') }}
                                            @else
                                                <x-lucide-check-circle class="size-4" />
                                                {{ __('ticket_categories.actions.activate') }}
                                            @endif
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @can('ticket_categories.delete')
                                        <x-admin.dropdown-separator />
                                        <x-admin.dropdown-item variant="destructive"
                                            @click="$wire.confirmDelete({{ $category->id }})">
                                            <x-lucide-trash class="size-4" />
                                            {{ __('ticket_categories.actions.delete') }}
                                        </x-admin.dropdown-item>
                                    @endcan
                                </x-admin.dropdown>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-tags class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('ticket_categories.index.empty') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">{{ __('ticket_categories.common.clear_filters') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$categories" />

    {{-- Bulk action bar (fixed bottom, shows when rows selected) --}}
    @if (count($selectedIds) > 0)
        <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background px-3 py-2 shadow-xl"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">
            <x-admin.tooltip :text="__('ticket_categories.common.clear_selection')">
                <x-ui.button variant="ghost" size="icon" class="size-8 rounded-full" wire:click="clearSelection">
                    <x-lucide-x class="size-4" />
                </x-ui.button>
            </x-admin.tooltip>

            <div class="mx-1 h-4 w-px bg-border"></div>
            <span class="px-1 text-sm font-medium">{{ __('ticket_categories.common.selected', ['count' => count($selectedIds)]) }}</span>
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
    @include('livewire.admin.support.categories.partials.dialogs')

</div>
