<div class="flex flex-col gap-6">

    {{-- Lifecycle waterfall --}}
    <x-ui.card>
        <x-ui.card-header>
            <x-ui.card-title class="text-sm">{{ __('api_logs.timeline.phases') }}</x-ui.card-title>
        </x-ui.card-header>
        <x-ui.card-content>
            <ol class="flex flex-col gap-2">
                @foreach ($phases as $index => $phase)
                    @php
                        $next = $phases[$index + 1]['at'] ?? $total;
                        $offset = min(100, $phase['at'] / $total * 100);
                        $width = max(0.75, min(100 - $offset, ($next - $phase['at']) / $total * 100));
                    @endphp
                    <li wire:key="phase-{{ $phase['key'] }}" class="grid grid-cols-[9rem_1fr_5rem] items-center gap-3 text-xs">
                        <span class="text-muted-foreground">{{ __('api_logs.timeline.'.$phase['key']) }}</span>
                        <span class="relative h-2.5 rounded bg-muted">
                            <span class="absolute inset-y-0 rounded bg-primary/70" style="left: {{ $offset }}%; width: {{ $width }}%"></span>
                        </span>
                        <span class="text-right font-mono tabular-nums">{{ __('api_logs.ms', ['value' => number_format($phase['at'], 1)]) }}</span>
                    </li>
                @endforeach
            </ol>
        </x-ui.card-content>
    </x-ui.card>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Database --}}
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title class="flex items-center gap-2 text-sm">
                    <x-lucide-database class="size-4 text-muted-foreground" />
                    {{ __('api_logs.timeline.database') }}
                </x-ui.card-title>
                <x-ui.card-description class="text-xs">
                    {{ __('api_logs.timeline.queries', ['count' => $log->db_query_count, 'ms' => number_format($log->db_time_ms, 2)]) }}
                </x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content>
                <p class="mb-2 text-xs font-semibold text-muted-foreground">{{ __('api_logs.timeline.slow_queries') }}</p>
                @forelse ($slowQueries as $index => $query)
                    <div wire:key="slow-query-{{ $index }}" class="mb-2 rounded-md border border-border p-2">
                        <p class="mb-1 font-mono text-xs text-amber-700 dark:text-amber-400">{{ __('api_logs.ms', ['value' => number_format($query['time_ms'], 2)]) }}</p>
                        <code class="block whitespace-pre-wrap break-all font-mono text-xs">{{ $query['sql'] }}</code>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('api_logs.timeline.no_slow_queries') }}</p>
                @endforelse
            </x-ui.card-content>
        </x-ui.card>

        {{-- Audit entries written by this request (or jobs it dispatched) --}}
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title class="flex items-center gap-2 text-sm">
                    <x-lucide-scroll-text class="size-4 text-muted-foreground" />
                    {{ __('api_logs.timeline.activity') }}
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content>
                @forelse ($activities as $index => $activity)
                    <div wire:key="activity-{{ $index }}" class="flex items-start gap-3 py-1.5">
                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full {{ $activity['colorClass'] }}">
                            <x-dynamic-component :component="'lucide-'.$activity['icon']" class="size-3.5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ $activity['title'] }}</p>
                            <p class="text-xs text-muted-foreground"><x-ui.local-time :value="$activity['at']" format="Y-m-d H:i:s" /></p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('api_logs.timeline.no_activity') }}</p>
                @endforelse
            </x-ui.card-content>
        </x-ui.card>
    </div>

    @if ($exceptions->isNotEmpty())
        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title class="flex items-center gap-2 text-sm">
                    <x-lucide-bug class="size-4 text-rose-500" />
                    {{ __('api_logs.timeline.exceptions') }}
                </x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-2">
                @foreach ($exceptions as $exception)
                    <button type="button" wire:key="timeline-exception-{{ $exception->id }}" wire:click="selectTab('exceptions')"
                        class="rounded-md border border-border p-2 text-left hover:bg-muted/40">
                        <span class="block font-mono text-xs text-rose-700 dark:text-rose-400">{{ $exception->class }}</span>
                        <span class="block truncate text-sm">{{ $exception->message }}</span>
                    </button>
                @endforeach
            </x-ui.card-content>
        </x-ui.card>
    @endif

</div>
