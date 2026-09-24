<div class="flex flex-col gap-6" @if ($live) wire:poll.5s @endif>

    {{-- Page header --}}
    <x-admin.page-header :title="__('api_logs.requests.title')" :description="__('api_logs.requests.subtitle')"
        :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('api_logs.title')], ['label' => __('api_logs.requests.title')]]">
        <x-slot:actions>
            <x-ui.button :variant="$live ? 'secondary' : 'outline'" size="sm" wire:click="toggleLive">
                <span @class(['size-2 rounded-full', 'animate-pulse bg-emerald-500' => $live, 'bg-muted-foreground/40' => ! $live])></span>
                {{ $live ? __('api_logs.requests.live_on') : __('api_logs.requests.live') }}
            </x-ui.button>
            @can('api_logs.analytics.view')
                <x-ui.button variant="outline" size="sm" href="{{ route('admin.api-logs.analytics') }}" wire:navigate>
                    <x-lucide-chart-no-axes-combined class="size-4" />
                    {{ __('navigation.modules.api_log_analytics') }}
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <x-admin.stat-card :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :description="$stat['description']" />
        @endforeach
    </div>

    {{-- Filters --}}
    <x-admin.filter-bar :config="$this->filterBarConfig()" :filters="$filters" :has-active-filters="$this->hasActiveFilters()"
        :search-placeholder="__('api_logs.requests.search')" />

    {{-- Table --}}
    <div class="overflow-x-auto rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('created_at')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('api_logs.fields.time') }}
                            @if ($sortBy === 'created_at')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('api_logs.fields.request_id') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('api_logs.fields.path') }}</th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('status_code')" class="flex items-center gap-1 font-medium text-foreground">
                            {{ __('api_logs.fields.status') }}
                            @if ($sortBy === 'status_code')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-right">
                        <button wire:click="sort('duration_ms')" class="ml-auto flex items-center gap-1 font-medium text-foreground">
                            {{ __('api_logs.fields.duration') }}
                            @if ($sortBy === 'duration_ms')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">{{ __('api_logs.fields.user') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground xl:table-cell">{{ __('api_logs.fields.ip') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($requests as $log)
                    <tr wire:key="api-request-{{ $log->id }}" class="group hover:bg-muted/30">
                        <td class="whitespace-nowrap px-4 py-3 text-muted-foreground">
                            <x-ui.local-time :value="$log->created_at" show-diff="true" />
                        </td>

                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('admin.api-logs.requests.show', $log->request_id) }}" wire:navigate
                                    class="font-mono text-xs font-medium text-primary hover:underline">{{ $log->request_id }}</a>
                                <x-admin.copy-button :value="$log->request_id" class="opacity-0 group-hover:opacity-100" />
                            </div>
                        </td>

                        <td class="max-w-md px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2">
                                <x-admin.api-logs.method-badge :method="$log->method" class="w-12 shrink-0" />
                                <span class="truncate font-mono text-xs" title="{{ $log->path }}">{{ $log->path }}</span>
                                @if ($log->has_exception)
                                    <x-lucide-bug class="size-3.5 shrink-0 text-rose-500" />
                                @endif
                            </div>
                            @if ($log->error_code)
                                <p class="ml-14 mt-0.5 font-mono text-[11px] text-muted-foreground">{{ $log->error_code }}</p>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <x-admin.api-logs.status-badge :code="$log->status_code" />
                        </td>

                        <td @class([
                            'whitespace-nowrap px-4 py-3 text-right font-mono text-xs tabular-nums',
                            'text-rose-600 dark:text-rose-400' => $log->duration_ms >= config('api_logs.slow_request_ms'),
                            'text-muted-foreground' => $log->duration_ms < config('api_logs.slow_request_ms'),
                        ])>
                            {{ __('api_logs.ms', ['value' => number_format($log->duration_ms)]) }}
                        </td>

                        <td class="hidden px-4 py-3 lg:table-cell">
                            @if ($log->user)
                                <p class="truncate text-sm">{{ $log->user->name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ $log->user->email }}</p>
                            @elseif ($log->user_id)
                                <span class="text-xs text-muted-foreground">#{{ $log->user_id }}</span>
                            @else
                                <span class="text-xs text-muted-foreground">—</span>
                            @endif
                        </td>

                        <td class="hidden whitespace-nowrap px-4 py-3 font-mono text-xs text-muted-foreground xl:table-cell">{{ $log->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-activity class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">{{ __('api_logs.requests.empty') }}</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters" class="mt-1 text-xs underline hover:no-underline">{{ __('api_logs.requests.clear_filters') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-admin.pagination :paginator="$requests" />

</div>
