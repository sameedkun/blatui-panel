@php
    use Illuminate\Support\Str;

    // Map an action verb to a badge treatment so the log scans fast.
    $actionBadge = function (?string $event): array {
        return match ($event) {
            'banned', 'deleted', 'force_deleted', 'purged', 'failed' => ['variant' => 'destructive', 'class' => ''],
            'created' => ['variant' => 'default', 'class' => 'border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'],
            'restored', 'unbanned' => ['variant' => 'default', 'class' => 'border-0 bg-sky-500/15 text-sky-700 dark:text-sky-400'],
            'login', 'password_reset' => ['variant' => 'secondary', 'class' => ''],
            default => ['variant' => 'outline', 'class' => ''],
        };
    };
@endphp

<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header title="Activity Logs"
        description="Immutable audit trail of privileged actions — who did what, to whom."
        :breadcrumbs="[['label' => 'Home', 'url' => route('admin.dashboard')], ['label' => 'Activity Logs']]">
        @can('activity_logs.export')
            <x-slot:actions>
                <x-ui.button variant="primary" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                    <x-lucide-download class="size-4" />
                    Export
                </x-ui.button>
            </x-slot:actions>
        @endcan
    </x-admin.page-header>

    {{-- Subject scope banner (deep-linked "all activity for this record") --}}
    @if ($subjectType && $subjectId)
        <div class="flex items-center justify-between rounded-md border border-primary/40 bg-primary/5 px-4 py-2 text-sm">
            <span class="flex items-center gap-2">
                <x-lucide-filter class="size-4 text-primary" />
                Showing activity for
                <span class="font-medium">{{ class_basename($subjectType) }} #{{ $subjectId }}</span>
            </span>
            <button wire:click="clearSubject" class="text-xs text-muted-foreground underline hover:no-underline">
                Clear
            </button>
        </div>
    @endif

    {{-- ── Security signals (lead) ──────────────────────────────────────── --}}
    <div>
        <h2 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-muted-foreground">
            <x-lucide-shield-alert class="size-4" />
            Security signals
            <span class="font-normal text-muted-foreground/70">· last 24h</span>
        </h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <x-admin.stat-card label="Failed logins" :value="$signals['failed_logins']" icon="shield-x"
                description="Rejected credentials (24h)" />
            <x-admin.stat-card label="Destructive actions" :value="$signals['destructive']" icon="alert-triangle"
                description="Bans, deletes, purges, role changes" />
            <x-admin.stat-card label="Active admins" :value="$signals['active_causers']" icon="users"
                description="Distinct causers (24h)" />
        </div>
    </div>

    {{-- ── Activity pulse ───────────────────────────────────────────────── --}}
    <div>
        <h2 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-muted-foreground">
            <x-lucide-activity class="size-4" />
            Activity pulse
        </h2>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <x-admin.stat-card label="Today" :value="$pulse['today']" icon="calendar-check"
                description="Events since midnight" />
            <x-admin.stat-card label="This week" :value="$pulse['week']" icon="calendar-range"
                description="Events this week" />
            <x-admin.stat-card label="This month" :value="$pulse['month']" icon="calendar-days"
                description="Events this month" />
        </div>
    </div>

    {{-- ── Module breakdown (reflects the active filters) ───────────────── --}}
    @if (count($breakdown))
        <x-ui.card>
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm font-medium">Events by module</p>
                <span class="text-xs text-muted-foreground">{{ number_format($breakdownTotal) }} in view</span>
            </div>
            <div class="flex flex-col gap-2">
                @foreach ($breakdown as $row)
                    @php $pct = $breakdownTotal > 0 ? round($row['count'] / $breakdownTotal * 100) : 0; @endphp
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-24 shrink-0 truncate text-muted-foreground">{{ $row['label'] }}</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary" style="width: {{ max($pct, 2) }}%"></div>
                        </div>
                        <span class="w-16 shrink-0 text-right font-medium tabular-nums">{{ number_format($row['count']) }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    {{-- Toolbar --}}
    <x-admin.filter-bar :config="$filterBarConfig" :filters="$filters" :has-active-filters="$this->hasActiveFilters()"
        search-placeholder="Search description, properties…" />

    {{-- Table --}}
    <div class="overflow-x-auto rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('created_at')" class="flex items-center gap-1 font-medium text-foreground">
                            When
                            @if ($sortBy === 'created_at')
                                <x-dynamic-component :component="$sortDir === 'asc' ? 'lucide-arrow-up' : 'lucide-arrow-down'" class="size-3.5" />
                            @else
                                <x-lucide-arrow-up-down class="size-3.5 opacity-40" />
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Causer</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">Action</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">Module</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground lg:table-cell">Subject</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground xl:table-cell">Context</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($activities as $activity)
                    @php
                        $props = $activity->properties;
                        $badge = $actionBadge($activity->event);
                    @endphp
                    <tr wire:key="activity-row-{{ $activity->id }}"
                        wire:click="viewActivity({{ $activity->id }})"
                        class="group cursor-pointer hover:bg-muted/30">

                        {{-- When --}}
                        <td class="px-4 py-3 whitespace-nowrap">
                            <x-ui.local-time :value="$activity->created_at" show-diff="true" class="text-muted-foreground" />
                        </td>

                        {{-- Causer --}}
                        <td class="px-4 py-3">
                            @if ($activity->causer)
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $activity->causer->name }}</div>
                                    <div class="truncate text-xs text-muted-foreground">{{ $activity->causer->email }}</div>
                                </div>
                            @else
                                <x-ui.badge variant="outline" class="gap-1">
                                    <x-lucide-server-cog class="size-3" />
                                    System
                                </x-ui.badge>
                            @endif
                        </td>

                        {{-- Action --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <x-ui.badge :variant="$badge['variant']" class="{{ $badge['class'] }}">
                                    {{ Str::headline($activity->event ?? '—') }}
                                </x-ui.badge>
                                @if (($props['bulk'] ?? false))
                                    <x-admin.tooltip :text="'Bulk action · '.($props['count'] ?? '?').' records'">
                                        <x-ui.badge variant="secondary" class="gap-1">
                                            <x-lucide-layers class="size-3" />
                                            {{ $props['count'] ?? '' }}
                                        </x-ui.badge>
                                    </x-admin.tooltip>
                                @endif
                            </div>
                        </td>

                        {{-- Module --}}
                        <td class="hidden px-4 py-3 md:table-cell">
                            <span class="text-muted-foreground">
                                {{ Str::headline($props['module'] ?? '—') }}
                                @if (! empty($props['area']))
                                    <span class="text-xs">· {{ Str::headline($props['area']) }}</span>
                                @endif
                            </span>
                        </td>

                        {{-- Subject --}}
                        <td class="hidden px-4 py-3 lg:table-cell">
                            @if ($activity->subject_type)
                                <span class="font-mono text-xs text-muted-foreground">
                                    {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                                </span>
                            @else
                                <span class="text-xs text-muted-foreground/50">—</span>
                            @endif
                        </td>

                        {{-- Context --}}
                        <td class="hidden px-4 py-3 xl:table-cell">
                            <x-ui.badge variant="outline">{{ Str::headline($props['context'] ?? '—') }}</x-ui.badge>
                        </td>

                        {{-- View affordance --}}
                        <td class="px-4 py-3 text-right">
                            <x-lucide-chevron-right class="ml-auto size-4 text-muted-foreground/40 group-hover:text-muted-foreground" />
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-muted-foreground">
                            <x-lucide-clipboard-list class="mx-auto mb-2 size-8 opacity-30" />
                            <p class="text-sm">No activity found.</p>
                            @if ($this->hasActiveFilters())
                                <button wire:click="resetFilters"
                                    class="mt-1 text-xs underline hover:no-underline">Clear filters</button>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <x-admin.pagination :paginator="$activities" />

    {{-- ── Deep-linked detail modal ─────────────────────────────────────── --}}
    @include('livewire.admin.administration.activity-logs.partials.detail-dialog', [
        'activity' => $selectedActivity,
        'dialogId' => 'activity-detail',
        'closeMethod' => 'clearSelected',
        'showScopeLink' => true,
    ])

</div>
