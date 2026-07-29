<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="__('feedback.title')" :description="__('feedback.subtitle')" :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('feedback.title')]]" />

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
        :search-placeholder="__('feedback.filters.search')" />

    {{-- Table --}}
    <div class="overflow-hidden rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('feedback.fields.from') }}</th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('subject')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('feedback.fields.subject') }}
                            @if ($sortBy === 'subject')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground sm:table-cell">{{ __('feedback.fields.type') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('feedback.fields.status') }}</th>
                    <th class="hidden px-4 py-3 text-left md:table-cell">
                        <button wire:click="sort('created_at')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('feedback.fields.submitted') }}
                            @if ($sortBy === 'created_at')
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
                @forelse ($feedback as $item)
                    <tr wire:key="feedback-row-{{ $item->id }}" class="hover:bg-muted/30">

                        {{-- From --}}
                        <td class="px-4 py-3">
                            @if ($item->user)
                                <div class="min-w-0">
                                    <span class="truncate font-medium">{{ $item->user->name }}</span>
                                    <div class="truncate text-xs text-muted-foreground">{{ $item->user->email }}</div>
                                </div>
                            @else
                                <div class="min-w-0">
                                    <x-ui.badge variant="outline" class="gap-1">
                                        <x-lucide-user-x class="size-3" />
                                        {{ __('feedback.stats.anonymous') }}
                                    </x-ui.badge>
                                    @if ($item->email)
                                        <div class="mt-1 truncate text-xs text-muted-foreground">{{ $item->email }}</div>
                                    @endif
                                </div>
                            @endif
                        </td>

                        {{-- Subject --}}
                        <td class="px-4 py-3">
                            <span class="truncate font-medium">{{ $item->subject ?: __('feedback.empty.subject') }}</span>
                            <div class="line-clamp-1 max-w-md text-xs text-muted-foreground">{{ $item->message }}</div>
                        </td>

                        {{-- Type --}}
                        <td class="hidden px-4 py-3 sm:table-cell">
                            <x-ui.badge variant="secondary">{{ $item->type->label() }}</x-ui.badge>
                        </td>

                        {{-- Status --}}
                        <td class="px-4 py-3">
                            @if ($item->status === \App\Enum\FeedbackStatus::Resolved)
                                <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">{{ $item->status->label() }}</x-ui.badge>
                            @elseif ($item->status === \App\Enum\FeedbackStatus::New)
                                <x-ui.badge variant="default" class="border-0 bg-blue-500/15 text-blue-700 dark:text-blue-400">{{ $item->status->label() }}</x-ui.badge>
                            @elseif ($item->status === \App\Enum\FeedbackStatus::Ignored)
                                <x-ui.badge variant="outline">{{ $item->status->label() }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary">{{ $item->status->label() }}</x-ui.badge>
                            @endif
                        </td>

                        {{-- Submitted --}}
                        <td class="hidden px-4 py-3 text-xs text-muted-foreground md:table-cell">
                            <x-ui.local-time :value="$item->created_at" show-diff="true" />
                        </td>

                        {{-- Row actions --}}
                        <td class="px-4 py-3 text-right">
                            @can('feedback.manage')
                                <x-admin.tooltip :text="__('feedback.actions.view')">
                                    <x-ui.button variant="ghost" size="icon" class="size-8"
                                        href="{{ route('admin.feedback.show', $item) }}">
                                        <x-lucide-eye class="size-4" />
                                        <span class="sr-only">{{ __('feedback.actions.view') }}</span>
                                    </x-ui.button>
                                </x-admin.tooltip>
                            @endcan
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-message-square-quote class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('feedback.empty.feedback') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">{{ __('feedback.filters.clear') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$feedback" />

</div>
