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
        @if ($curl)
            <x-ui.card>
                <x-ui.card-header class="flex flex-row items-center justify-between">
                    <div>
                        <x-ui.card-title class="text-sm">{{ __('api_logs.actions.copy_curl') }}</x-ui.card-title>
                        <x-ui.card-description class="text-xs">{{ __('api_logs.show.curl_hint') }}</x-ui.card-description>
                    </div>
                    <x-admin.copy-button :value="$curl" :label="__('api_logs.actions.copy')" class="border border-border px-2 py-1.5" />
                </x-ui.card-header>
                <x-ui.card-content>
                    <pre class="max-h-64 overflow-auto whitespace-pre-wrap break-all rounded-md border border-border bg-muted/20 p-3 font-mono text-xs">{{ $curl }}</pre>
                </x-ui.card-content>
            </x-ui.card>
        @endif

        @if ($payload->query)
            <x-ui.card>
                <x-ui.card-header><x-ui.card-title class="text-sm">{{ __('api_logs.show.query') }}</x-ui.card-title></x-ui.card-header>
                <x-ui.card-content><x-admin.api-logs.json-block :value="$payload->query" /></x-ui.card-content>
            </x-ui.card>
        @endif

        <x-ui.card>
            <x-ui.card-header><x-ui.card-title class="text-sm">{{ __('api_logs.show.headers') }}</x-ui.card-title></x-ui.card-header>
            <x-ui.card-content><x-admin.api-logs.headers-table :headers="$payload->request_headers ?? []" /></x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header><x-ui.card-title class="text-sm">{{ __('api_logs.show.body') }}</x-ui.card-title></x-ui.card-header>
            <x-ui.card-content><x-admin.api-logs.json-block :value="$payload->request_body" :truncated="$payload->request_truncated" /></x-ui.card-content>
        </x-ui.card>
    @endif
</div>
