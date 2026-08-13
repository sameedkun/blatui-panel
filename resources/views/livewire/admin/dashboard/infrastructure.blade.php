<div class="flex flex-col gap-6">

    {{-- White-Label Fleet Banner --}}
    <x-ui.card class="relative overflow-hidden border-border/80 bg-gradient-to-r from-background via-muted/20 to-background p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <x-lucide-server-cog class="size-6" />
                </div>
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold tracking-tight">{{ __('dashboard.infrastructure.title') }}</h3>
                        <x-ui.badge variant="secondary" class="bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border border-indigo-500/20 px-2.5 py-0.5">
                            White-Label Ready
                        </x-ui.badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ __('dashboard.infrastructure.subtitle') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.button variant="outline" size="sm" class="gap-1.5 shadow-xs">
                    <x-lucide-plus class="size-3.5" />
                    <span>Add Node Fleet</span>
                </x-ui.button>
            </div>
        </div>
    </x-ui.card>

    {{-- Fleet KPI Cards Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        
        <x-ui.card>
            <x-ui.card-content class="flex flex-col p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Nodes Online</span>
                    <div class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                        <x-lucide-server class="size-4" />
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-2xl font-bold font-mono tracking-tight text-foreground">{{ $fleetStats['active_nodes'] }} / {{ $fleetStats['total_nodes'] }}</span>
                    <x-ui.badge variant="default" class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 text-[11px]">
                        91.6% Active
                    </x-ui.badge>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">Target infrastructure nodes</p>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-content class="flex flex-col p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Egress Bandwidth</span>
                    <div class="flex size-9 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <x-lucide-network class="size-4" />
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-2xl font-bold font-mono tracking-tight text-foreground">{{ $fleetStats['egress_bandwidth'] }}</span>
                    <x-ui.badge variant="outline" class="text-[11px]">Peak</x-ui.badge>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">Aggregated egress traffic</p>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-content class="flex flex-col p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Average Latency</span>
                    <div class="flex size-9 items-center justify-center rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
                        <x-lucide-zap class="size-4" />
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-2xl font-bold font-mono tracking-tight text-foreground">{{ $fleetStats['average_latency'] }}</span>
                    <x-ui.badge variant="secondary" class="text-[11px]">Edge RTT</x-ui.badge>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">Global node roundtrip</p>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-content class="flex flex-col p-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Fleet Capacity</span>
                    <div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <x-lucide-gauge class="size-4" />
                    </div>
                </div>
                <div class="mt-3 flex flex-col gap-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-mono font-bold text-foreground">{{ $fleetStats['fleet_utilization'] }}%</span>
                        <span class="text-muted-foreground text-[11px]">Capacity Load</span>
                    </div>
                    <x-ui.progress :value="$fleetStats['fleet_utilization']" class="h-2" />
                </div>
            </x-ui.card-content>
        </x-ui.card>

    </div>

    {{-- Regional Clusters Matrix --}}
    <x-ui.card>
        <x-ui.card-header class="flex flex-row items-center justify-between pb-4">
            <div class="flex flex-col gap-1">
                <x-ui.card-title class="flex items-center gap-2 text-sm font-semibold">
                    <x-lucide-globe-2 class="size-4 text-indigo-500" />
                    Regional Node Clusters
                </x-ui.card-title>
                <x-ui.card-description class="text-xs">Edge server deployment locations & real-time load</x-ui.card-description>
            </div>
            <x-ui.badge variant="outline" class="font-mono text-xs">
                Clusters: {{ count($regionalClusters) }}
            </x-ui.badge>
        </x-ui.card-header>
        <x-ui.card-content class="pt-0">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($regionalClusters as $cluster)
                    <div class="flex flex-col gap-2.5 rounded-xl border border-border/80 bg-muted/10 p-4 transition-all hover:border-border">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-foreground truncate">{{ $cluster['name'] }}</span>
                            @if ($cluster['status'] === 'online')
                                <x-ui.badge variant="default" class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 text-[10px] uppercase">
                                    Online
                                </x-ui.badge>
                            @elseif ($cluster['status'] === 'maintenance')
                                <x-ui.badge variant="destructive" class="text-[10px] uppercase">
                                    Maintenance
                                </x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary" class="text-[10px] uppercase">
                                    Standby
                                </x-ui.badge>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs border-t border-b border-border/50 py-2">
                            <div>
                                <span class="text-[11px] text-muted-foreground">Nodes</span>
                                <p class="font-mono font-bold text-foreground">{{ $cluster['nodes'] }}</p>
                            </div>
                            <div>
                                <span class="text-[11px] text-muted-foreground">Latency</span>
                                <p class="font-mono font-bold text-foreground">{{ $cluster['latency'] }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1 text-xs">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-muted-foreground">Cluster Load</span>
                                <span class="font-mono font-medium text-foreground">{{ $cluster['load'] }}%</span>
                            </div>
                            <x-ui.progress :value="$cluster['load']" class="h-1.5" />
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.card-content>
    </x-ui.card>

    {{-- Integration Slot & White-Label Extensibility --}}
    <x-ui.card>
        <x-ui.empty class="py-12">
            <x-ui.empty-header>
                <x-ui.empty-media variant="icon" class="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <x-lucide-plug-zap class="size-6" />
                </x-ui.empty-media>
                <x-ui.empty-title>{{ __('dashboard.infrastructure.empty_title') }}</x-ui.empty-title>
                <x-ui.empty-description class="max-w-md">
                    {{ __('dashboard.infrastructure.empty_description') }}
                </x-ui.empty-description>
            </x-ui.empty-header>
        </x-ui.empty>
    </x-ui.card>

</div>
