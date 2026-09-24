@php($first = $exceptions->first())

<div class="flex flex-col gap-6">
    <x-ui.alert>
        <x-lucide-hourglass class="size-4" />
        <x-ui.alert-title>{{ __('api_logs.show.expired_title') }}</x-ui.alert-title>
        <x-ui.alert-description>
            {{ __('api_logs.show.expired_description', ['days' => config('api_logs.retention.raw_days'), 'exception_days' => config('api_logs.retention.exception_days')]) }}
        </x-ui.alert-description>
    </x-ui.alert>

    @if ($first)
        <x-ui.card>
            <x-ui.card-content>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.route') }}</dt>
                        <dd class="flex items-center gap-2 font-mono text-sm">
                            <x-admin.api-logs.method-badge :method="$first->method" />
                            {{ $first->route_uri === \App\Models\ApiLog\ApiRequestLog::UNMATCHED_ROUTE ? __('api_logs.unmatched_route') : '/'.$first->route_uri }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.status') }}</dt>
                        <dd>@if ($first->status_code)<x-admin.api-logs.status-badge :code="$first->status_code" />@else — @endif</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.time') }}</dt>
                        <dd class="text-sm"><x-ui.local-time :value="$first->created_at" format="Y-m-d H:i:s" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.correlation_id') }}</dt>
                        <dd class="flex items-center gap-1 font-mono text-xs">
                            {{ $first->correlation_id ?? '—' }}
                            @if ($first->correlation_id)<x-admin.copy-button :value="$first->correlation_id" />@endif
                        </dd>
                    </div>
                </dl>
            </x-ui.card-content>
        </x-ui.card>
    @endif
</div>
