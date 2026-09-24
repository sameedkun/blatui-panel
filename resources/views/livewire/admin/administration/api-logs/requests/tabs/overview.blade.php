@php
    /** @var \App\Models\ApiLog\ApiRequestLog $log */
    $formatBytes = fn (?int $bytes): string => \App\Models\ApiLog\ApiRequestLog::formatBytes($bytes);
    $summary = [
        __('api_logs.fields.route') => $log->route_uri === \App\Models\ApiLog\ApiRequestLog::UNMATCHED_ROUTE ? __('api_logs.unmatched_route') : '/'.$log->route_uri,
        __('api_logs.fields.route_name') => $log->route_name ?? '—',
        __('api_logs.fields.version') => $log->api_version ?? '—',
        __('api_logs.fields.duration') => __('api_logs.ms', ['value' => number_format($log->duration_ms, 2)]),
        __('api_logs.fields.db_queries') => __('api_logs.timeline.queries', ['count' => $log->db_query_count, 'ms' => number_format($log->db_time_ms, 2)]),
        __('api_logs.fields.memory') => $log->memory_peak_kb ? $formatBytes($log->memory_peak_kb * 1024) : '—',
        __('api_logs.fields.request_size') => $formatBytes($log->request_size),
        __('api_logs.fields.response_size') => $formatBytes($log->response_size),
        __('api_logs.fields.client_type') => __('api_logs.client_types.'.($log->client_type ?? 'app')),
    ];
@endphp

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

    <div class="flex flex-col gap-6 xl:col-span-2">
        {{-- Exception callout --}}
        @if ($exceptions->isNotEmpty())
            @php $first = $exceptions->first(); @endphp
            <button type="button" wire:click="selectTab('exceptions')"
                class="flex w-full items-start gap-3 rounded-md border border-rose-500/30 bg-rose-500/5 p-4 text-left transition-colors hover:bg-rose-500/10">
                <x-lucide-bug class="mt-0.5 size-4 shrink-0 text-rose-600" />
                <span class="min-w-0">
                    <span class="block font-mono text-xs text-rose-700 dark:text-rose-400">{{ $first->class }}</span>
                    <span class="mt-0.5 block break-words text-sm">{{ $first->message }}</span>
                    @if ($first->file)
                        <span class="mt-1 block font-mono text-xs text-muted-foreground">{{ $first->file }}:{{ $first->line }}</span>
                    @endif
                </span>
                <x-lucide-chevron-right class="ml-auto size-4 shrink-0 text-muted-foreground" />
            </button>
        @endif

        <x-ui.card>
            <x-ui.card-header>
                <x-ui.card-title class="text-sm">{{ __('api_logs.show.summary') }}</x-ui.card-title>
            </x-ui.card-header>
            <x-ui.card-content>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.status') }}</dt>
                        <dd class="mt-0.5 flex items-center gap-2">
                            <x-admin.api-logs.status-badge :code="$log->status_code" />
                            @if ($log->error_code)
                                <span class="font-mono text-xs">{{ $log->error_code }}</span>
                            @endif
                        </dd>
                    </div>
                    @foreach ($summary as $label => $value)
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ $label }}</dt>
                            <dd class="mt-0.5 break-all text-sm">{{ $value }}</dd>
                        </div>
                    @endforeach
                    @if ($log->sample_weight > 1)
                        <div>
                            <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.sample_weight') }}</dt>
                            <dd class="mt-0.5 text-sm">{{ __('api_logs.requests.sampled', ['weight' => $log->sample_weight]) }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Correlation --}}
        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between">
                <x-ui.card-title class="text-sm">{{ __('api_logs.show.related') }}</x-ui.card-title>
                @if ($related->isNotEmpty())
                    <x-ui.button variant="ghost" size="sm" href="{{ route('admin.api-logs.requests.index', ['correlation' => $log->correlation_id]) }}" wire:navigate>
                        {{ __('api_logs.actions.view_correlation') }}
                        <x-lucide-arrow-right class="size-3.5" />
                    </x-ui.button>
                @endif
            </x-ui.card-header>
            <x-ui.card-content>
                @if ($related->isEmpty())
                    <p class="text-sm text-muted-foreground">{{ __('api_logs.show.related_empty') }}</p>
                @else
                    <ol class="relative ml-2 border-l border-border">
                        @foreach ($related->push($log)->sortBy('created_at') as $item)
                            @php $isCurrent = $item->request_id === $log->request_id; @endphp
                            <li wire:key="related-{{ $item->request_id }}" class="relative py-1.5 pl-5">
                                <span @class(['absolute -left-[5px] top-3 size-2.5 rounded-full border-2 border-background', 'bg-primary' => $isCurrent, 'bg-muted-foreground/40' => ! $isCurrent])></span>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                    <x-admin.api-logs.method-badge :method="$item->method" />
                                    @if ($isCurrent)
                                        <span class="font-mono font-semibold">{{ $item->path }}</span>
                                    @else
                                        <a href="{{ route('admin.api-logs.requests.show', $item->request_id) }}" wire:navigate class="font-mono hover:underline">{{ $item->path }}</a>
                                    @endif
                                    <x-admin.api-logs.status-badge :code="$item->status_code" class="text-[10px]" />
                                    <span class="ml-auto text-muted-foreground"><x-ui.local-time :value="$item->created_at" format="Y-m-d H:i:s" /></span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card-content>
        </x-ui.card>
    </div>

    {{-- Identifiers --}}
    <x-ui.card class="h-fit">
        <x-ui.card-header>
            <x-ui.card-title class="text-sm">{{ __('api_logs.show.identifiers') }}</x-ui.card-title>
        </x-ui.card-header>
        <x-ui.card-content>
            <dl class="flex flex-col gap-4">
                @foreach ([__('api_logs.fields.request_id') => $log->request_id, __('api_logs.fields.correlation_id') => $log->correlation_id] as $label => $value)
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ $label }}</dt>
                        <dd class="mt-0.5 flex items-center gap-1 font-mono text-xs">
                            <span class="break-all">{{ $value }}</span>
                            <x-admin.copy-button :value="$value" />
                        </dd>
                    </div>
                @endforeach
                <div>
                    <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.ip') }}</dt>
                    <dd class="mt-0.5 flex items-center gap-2 font-mono text-xs">
                        {{ $log->ip ?? '—' }}
                        @if ($log->ip)
                            <a href="{{ route('admin.api-logs.requests.index', ['ip' => $log->ip]) }}" wire:navigate class="text-primary hover:underline">
                                <x-lucide-arrow-right class="size-3.5" />
                                <span class="sr-only">{{ __('api_logs.actions.view_ip') }}</span>
                            </a>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.user_agent') }}</dt>
                    <dd class="mt-0.5 break-all text-xs">{{ $log->user_agent ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui.card-content>
    </x-ui.card>

</div>
