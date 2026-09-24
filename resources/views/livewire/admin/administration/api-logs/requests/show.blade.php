<div class="flex flex-col gap-6">

    <x-admin.page-header :title="$this->title()" :breadcrumbs="$this->breadcrumbs()">
        <x-slot:actions>
            <x-admin.copy-button :value="$requestId" :label="__('api_logs.actions.copy')" class="border border-border px-2 py-1.5" />
            @if ($log)
                <x-admin.api-logs.status-badge :code="$log->status_code" class="text-sm" />
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Request line --}}
    @if ($log)
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border border-border bg-muted/20 px-4 py-3">
            <x-admin.api-logs.method-badge :method="$log->method" class="text-sm" />
            <span class="break-all font-mono text-sm">{{ $log->path }}</span>
            <span class="ml-auto flex items-center gap-3 text-xs text-muted-foreground">
                <span class="flex items-center gap-1"><x-lucide-timer class="size-3.5" />{{ __('api_logs.ms', ['value' => number_format($log->duration_ms, 1)]) }}</span>
                <span class="flex items-center gap-1"><x-lucide-clock class="size-3.5" /><x-ui.local-time :value="$log->created_at" format="Y-m-d H:i:s" /></span>
            </span>
        </div>
    @endif

    <x-admin.show-tabs :tabs="$tabs" :active="$active">
        @if ($this->activeTabView())
            @include($this->activeTabView(), ['log' => $log, 'exceptions' => $exceptions, ...$this->activeTabData()])
        @endif
    </x-admin.show-tabs>

</div>
