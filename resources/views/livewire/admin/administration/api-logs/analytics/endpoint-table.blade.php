{{--
    One ranked endpoint table (slowest / most errors / busiest).

    Expects:
      $rows     list<array>  from ApiLogAnalytics::endpoints()
      $title    string
      $icon     string
      $columns  list<string> which metric columns to show, in order
                (requests, avg, p95, max, errors_4xx, errors_5xx, error_rate)
--}}
<x-ui.card>
    <x-ui.card-header class="pb-3">
        <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
            <x-dynamic-component :component="'lucide-'.$icon" class="size-4 text-muted-foreground" />
            {{ $title }}
        </x-ui.card-title>
    </x-ui.card-header>
    <x-ui.card-content class="px-0">
        @if ($rows === [])
            <p class="py-6 text-center text-xs text-muted-foreground">{{ __('api_logs.analytics.no_data') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-y border-border bg-muted/40 text-muted-foreground">
                            <th class="px-4 py-2 text-left font-medium">{{ __('api_logs.analytics.columns.endpoint') }}</th>
                            @foreach ($columns as $column)
                                <th class="px-3 py-2 text-right font-medium">{{ __('api_logs.analytics.columns.'.$column) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($rows as $row)
                            <tr wire:key="endpoint-{{ md5($title.$row['method'].$row['route_uri']) }}" class="hover:bg-muted/30">
                                <td class="max-w-xs px-4 py-2">
                                    <a href="{{ route('admin.api-logs.requests.index', ['route' => $row['route_uri']]) }}" wire:navigate
                                        class="flex min-w-0 items-center gap-2 hover:underline">
                                        <x-admin.api-logs.method-badge :method="$row['method']" class="w-12 shrink-0" />
                                        <span class="truncate font-mono">{{ $row['route_uri'] === \App\Models\ApiLog\ApiRequestLog::UNMATCHED_ROUTE ? __('api_logs.unmatched_route') : '/'.$row['route_uri'] }}</span>
                                    </a>
                                </td>
                                @foreach ($columns as $column)
                                    @php
                                        $value = $row[$column === 'avg' ? 'avg_ms' : ($column === 'p95' ? 'p95_ms' : ($column === 'max' ? 'max_ms' : $column))];
                                    @endphp
                                    <td @class([
                                        'whitespace-nowrap px-3 py-2 text-right font-mono tabular-nums',
                                        'text-rose-600 dark:text-rose-400' => $column === 'errors_5xx' && $value > 0,
                                        'text-amber-600 dark:text-amber-400' => $column === 'errors_4xx' && $value > 0,
                                    ])>
                                        @if ($value === null)
                                            —
                                        @elseif (in_array($column, ['avg', 'p95', 'max'], true))
                                            {{ __('api_logs.ms', ['value' => number_format($value)]) }}
                                        @elseif ($column === 'error_rate')
                                            {{ $value }}%
                                        @else
                                            {{ number_format($value) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card-content>
</x-ui.card>
