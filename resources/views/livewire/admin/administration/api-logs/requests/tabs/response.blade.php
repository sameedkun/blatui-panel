@php($payload = $log->payload)

<div class="flex flex-col gap-6">
    <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
        <x-lucide-shield-check class="size-3.5" />
        {{ __('api_logs.show.sanitized_notice') }}
    </p>

    @if (! $payload)
        <x-ui.alert>
            <x-lucide-info class="size-4" />
            <x-ui.alert-description>{{ __('api_logs.show.no_payload') }}</x-ui.alert-description>
        </x-ui.alert>
    @else
        <x-ui.card>
            <x-ui.card-header><x-ui.card-title class="text-sm">{{ __('api_logs.show.headers') }}</x-ui.card-title></x-ui.card-header>
            <x-ui.card-content><x-admin.api-logs.headers-table :headers="$payload->response_headers ?? []" /></x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between">
                <x-ui.card-title class="text-sm">{{ __('api_logs.show.body') }}</x-ui.card-title>
                <x-admin.api-logs.status-badge :code="$log->status_code" />
            </x-ui.card-header>
            <x-ui.card-content><x-admin.api-logs.json-block :value="$payload->response_body" :truncated="$payload->response_truncated" /></x-ui.card-content>
        </x-ui.card>
    @endif
</div>
