@php
    $degraded = $health['recentFailures'] > 0;
@endphp

<div class="flex flex-col gap-6">

    {{-- System Health Overview Banner --}}
    <x-ui.card class="relative overflow-hidden border-border/80 bg-gradient-to-r from-background via-muted/30 to-background p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <x-lucide-cpu class="size-6" />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold tracking-tight">System & Application Runtime Telemetry</h3>
                        <x-ui.badge :variant="$degraded ? 'destructive' : 'default'" class="gap-1.5 px-2.5 py-0.5">
                            <span @class([
                                'size-2 rounded-full',
                                'bg-rose-500 animate-pulse' => $degraded,
                                'bg-emerald-400' => ! $degraded,
                            ])></span>
                            {{ $degraded ? __('dashboard.health.degraded') : __('dashboard.health.operational') }}
                        </x-ui.badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Live environment configuration, queue worker fleet status, database statistics, and scheduled tasks.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex flex-col text-right sm:block">
                    <span class="text-xs text-muted-foreground">{{ __('dashboard.labels.last_scheduled_run') }}:</span>
                    <span class="text-xs font-medium text-foreground ml-1">
                        {{ $health['lastScheduledRun']?->diffForHumans() ?? __('dashboard.labels.never') }}
                    </span>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Environment & Core Specs Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-3">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-code class="size-4 text-primary" />
                    {{ __('dashboard.widgets.system_runtime') }}
                </x-ui.card-title>
                <x-ui.badge variant="outline" class="font-mono text-xs uppercase">{{ $info['environment'] }}</x-ui.badge>
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-2.5 pt-0 text-xs">
                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                    <span class="text-muted-foreground">PHP Version</span>
                    <span class="font-mono font-semibold text-foreground">{{ $info['php_version'] }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                    <span class="text-muted-foreground">Laravel Framework</span>
                    <span class="font-mono font-semibold text-foreground">v{{ $info['laravel_version'] }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                    <span class="text-muted-foreground">Database Engine</span>
                    <span class="font-mono font-semibold uppercase text-foreground">{{ $info['db_driver'] }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground">Cache & Queue Drivers</span>
                    <span class="font-mono font-semibold uppercase text-foreground">{{ $info['cache_driver'] }} / {{ $info['queue_driver'] }}</span>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header class="flex flex-row items-center justify-between pb-3">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-database class="size-4 text-emerald-500" />
                    {{ __('dashboard.widgets.system_database') }}
                </x-ui.card-title>
                <x-ui.badge variant="secondary" class="text-xs">Indexed</x-ui.badge>
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-2.5 pt-0 text-xs">
                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                    <span class="text-muted-foreground">Total Users (App/Staff/Guest)</span>
                    <span class="font-mono font-bold text-foreground">{{ number_format($dbStats['users']) }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                    <span class="text-muted-foreground">Subscriptions</span>
                    <span class="font-mono font-bold text-foreground">{{ number_format($dbStats['subscriptions']) }}</span>
                </div>
                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                    <span class="text-muted-foreground">Support Tickets</span>
                    <span class="font-mono font-bold text-foreground">{{ number_format($dbStats['tickets']) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-muted-foreground">Audit Activity Log Rows</span>
                    <span class="font-mono font-bold text-foreground">{{ number_format($dbStats['activity_log']) }}</span>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card class="sm:col-span-2 lg:col-span-1">
            <x-ui.card-header class="flex flex-row items-center justify-between pb-3">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                    <x-lucide-layers class="size-4 text-amber-500" />
                    Queue Fleet & Background Workers
                </x-ui.card-title>
                <x-ui.badge :variant="$health['queued'] > 50 ? 'destructive' : 'secondary'">
                    {{ $health['queued'] }} Queued
                </x-ui.badge>
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-3 pt-0 text-xs">
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="rounded-lg border border-border/70 bg-muted/20 p-2.5 text-center">
                        <span class="text-[11px] text-muted-foreground">Processing</span>
                        <p class="mt-0.5 text-lg font-bold text-foreground tabular-nums">{{ number_format($health['reserved']) }}</p>
                    </div>
                    <div class="rounded-lg border border-border/70 bg-muted/20 p-2.5 text-center">
                        <span class="text-[11px] text-muted-foreground">Failures (24h)</span>
                        <p @class([
                            'mt-0.5 text-lg font-bold tabular-nums',
                            'text-rose-600 dark:text-rose-400' => $health['recentFailures'] > 0,
                            'text-foreground' => $health['recentFailures'] === 0,
                        ])>{{ number_format($health['recentFailures']) }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-border/50 pt-2">
                    <span class="text-muted-foreground">Oldest Job Wait Time</span>
                    <span class="font-mono font-medium text-foreground">
                        {{ $health['oldestWait'] !== null ? $health['oldestWait'] . 's' : '0s' }}
                    </span>
                </div>
            </x-ui.card-content>
        </x-ui.card>

    </div>

    {{-- Recent Failed Jobs Monitor --}}
    <x-ui.card>
        <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
            <x-ui.card-title class="flex items-center gap-2 text-sm font-medium">
                <x-lucide-alert-triangle class="size-4 text-rose-500" />
                Recent Failed Jobs Log
            </x-ui.card-title>
            <x-ui.badge variant="outline" class="text-xs tabular-nums font-mono">
                Total: {{ number_format($health['failed']) }}
            </x-ui.badge>
        </x-ui.card-header>
        <x-ui.card-content class="pt-0">
            @if ($recentFailuresList->isNotEmpty())
                <div class="flex flex-col divide-y divide-border/50">
                    @foreach ($recentFailuresList as $failed)
                        <div class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="truncate font-mono font-medium text-rose-600 dark:text-rose-400">
                                    {{ Str::afterLast($failed->queue ?? 'default', '/') }} &middot; {{ $failed->uuid }}
                                </span>
                                <span class="shrink-0 text-muted-foreground font-mono">
                                    {{ \Illuminate\Support\Carbon::parse($failed->failed_at)->diffForHumans() }}
                                </span>
                            </div>
                            <p class="line-clamp-2 font-mono text-[11px] text-muted-foreground bg-muted/40 rounded p-2 border border-border/40">
                                {{ Str::limit($failed->exception, 250) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="flex size-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mb-2">
                        <x-lucide-check-circle-2 class="size-5" />
                    </div>
                    <p class="text-xs font-medium text-foreground">No failed jobs recorded</p>
                    <p class="text-[11px] text-muted-foreground mt-0.5">All queue workers are executing cleanly without exceptions.</p>
                </div>
            @endif
        </x-ui.card-content>
    </x-ui.card>

</div>
