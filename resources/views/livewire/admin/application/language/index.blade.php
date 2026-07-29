<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('languages.title')" :description="__('languages.subtitle')" :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('languages.title')]]">
        @can('languages.create')
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.languages.create') }}">
                    <x-lucide-plus class="size-4" />
                    {{ __('languages.actions.create') }}
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
        :search-placeholder="__('languages.filters.search')" />

    {{-- Table --}}
    @php
        $canBulkDelete = auth()->user()->can('languages.delete');
        $canRowAct = auth()->user()->canAny(['languages.edit', 'languages.delete']);
    @endphp
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="w-10 px-4 py-3 text-left">
                        @if ($canBulkDelete)
                            <input type="checkbox" x-data
                                :checked="@js($pageIds).length > 0 && @js($pageIds).every(id => $wire
                                    .selectedIds.includes(id))"
                                @change="$wire.toggleSelectPage(@js($pageIds))"
                                class="blat-checkbox cursor-pointer dark:bg-input/30" />
                        @endif
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('name')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('languages.fields.language') }}
                            @if ($sortBy === 'name')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('languages.fields.code') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('languages.fields.status') }}</th>
                    <th class="hidden px-4 py-3 text-left md:table-cell">
                        <button wire:click="sort('sort_order')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('languages.fields.order') }}
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
                @forelse ($languages as $language)
                    <tr wire:key="language-row-{{ $language->id }}"
                        class="hover:bg-muted/30 {{ in_array((string) $language->id, $this->selectedIds) ? 'bg-muted/20' : '' }}">

                        {{-- Checkbox (default language isn't bulk-selectable) --}}
                        <td class="px-4 py-3">
                            @if ($canBulkDelete && ! $language->is_default)
                                <input type="checkbox" x-data :checked="$wire.selectedIds.includes('{{ $language->id }}')"
                                    @change="$wire.toggleSelection('{{ $language->id }}')"
                                    class="blat-checkbox cursor-pointer dark:bg-input/30" />
                            @endif
                        </td>

                        {{-- Language --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                @if ($language->flagEmoji())
                                    <span class="text-base leading-none">{{ $language->flagEmoji() }}</span>
                                @else
                                    <x-lucide-globe class="size-4 text-muted-foreground" />
                                @endif
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate font-medium">{{ $language->name }}</span>
                                        @if ($language->is_default)
                                            <x-ui.badge variant="outline" class="gap-1">
                                                <x-lucide-star class="size-3" />
                                                {{ __('languages.status.default') }}
                                            </x-ui.badge>
                                        @endif
                                        @if ($language->is_rtl)
                                            <x-ui.badge variant="secondary">{{ __('languages.status.rtl') }}</x-ui.badge>
                                        @endif
                                    </div>
                                    @if ($language->native_name)
                                        <span class="truncate text-xs text-muted-foreground">{{ $language->native_name }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Code --}}
                        <td class="px-4 py-3">
                            <x-ui.badge variant="secondary" class="font-mono">{{ $language->code }}</x-ui.badge>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if ($language->is_active)
                                <x-ui.badge variant="default"
                                    class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ __('languages.status.active') }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="outline">{{ __('languages.status.inactive') }}</x-ui.badge>
                            @endif
                        </td>

                        {{-- Order --}}
                        <td class="hidden px-4 py-3 text-muted-foreground md:table-cell">{{ $language->sort_order }}</td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            @if ($canRowAct)
                                <x-admin.dropdown align="end" width="w-40">
                                    <x-slot:trigger>
                                        <x-ui.button variant="ghost" size="icon" class="size-8">
                                            <x-lucide-ellipsis class="size-4" />
                                            <span class="sr-only">{{ __('common.actions') }}</span>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    @can('languages.edit')
                                        <x-admin.dropdown-item href="{{ route('admin.languages.edit', $language) }}">
                                            <x-lucide-pencil class="size-4" />
                                            {{ __('languages.actions.edit') }}
                                        </x-admin.dropdown-item>
                                    @endcan

                                    @can('languages.delete')
                                        @if (! $language->is_default)
                                            <x-admin.dropdown-separator />
                                            <x-admin.dropdown-item variant="destructive"
                                                @click="$wire.confirmDelete({{ $language->id }})">
                                                <x-lucide-trash class="size-4" />
                                                {{ __('languages.actions.delete') }}
                                            </x-admin.dropdown-item>
                                        @endif
                                    @endcan
                                </x-admin.dropdown>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-globe class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('languages.empty') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">{{ __('languages.filters.clear') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$languages" />

    {{-- Bulk action bar (fixed bottom, shows when rows selected) --}}
    @if (count($selectedIds) > 0)
        <div class="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background px-3 py-2 shadow-xl"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0">
            <x-admin.tooltip :text="__('languages.actions.clear_selection')">
                <x-ui.button variant="ghost" size="icon" class="size-8 rounded-full" wire:click="clearSelection">
                    <x-lucide-x class="size-4" />
                </x-ui.button>
            </x-admin.tooltip>

            <div class="mx-1 h-4 w-px bg-border"></div>
            <span class="px-1 text-sm font-medium">{{ trans_choice('languages.actions.selected', count($selectedIds), ['count' => count($selectedIds)]) }}</span>
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
    @include('livewire.admin.application.language.partials.dialogs')

</div>
