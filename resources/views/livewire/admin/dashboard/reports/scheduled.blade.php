{{-- Recurring report schedules. --}}
@php
    $canManage = auth()->user()->can('dashboard.reports.manage');
@endphp

<div class="flex flex-col gap-3">
    @forelse ($schedules as $schedule)
        @php $definition = $definitions[$schedule->report] ?? null; @endphp
        <x-ui.card wire:key="schedule-{{ $schedule->id }}" class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <x-dynamic-component :component="'lucide-'.($definition?->icon() ?? 'file-text')" class="size-5" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="truncate font-semibold">{{ $schedule->name }}</p>
                    @if ($schedule->is_active)
                        <x-ui.badge tone="success">{{ __('dashboard.reports.schedule_status.active') }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral">{{ __('dashboard.reports.schedule_status.paused') }}</x-ui.badge>
                    @endif
                </div>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    {{ $definition?->label() }} · {{ $schedule->cadenceLabel() }}
                </p>
                <p class="mt-1 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground">
                    <x-ui.badge variant="outline" size="sm" class="font-mono uppercase">{{ $schedule->format->value }}</x-ui.badge>
                    <x-lucide-arrow-right class="size-3" />
                    <span class="truncate">{{ implode(', ', $schedule->recipients) }}</span>
                </p>
            </div>

            <dl class="grid shrink-0 grid-cols-2 gap-x-6 gap-y-1 text-xs sm:text-right">
                <dt class="text-muted-foreground">{{ __('dashboard.reports.next_run') }}</dt>
                <dd>
                    @if ($schedule->is_active && $schedule->next_run_at)
                        <x-ui.local-time :value="$schedule->next_run_at" />
                    @else
                        —
                    @endif
                </dd>
                <dt class="text-muted-foreground">{{ __('dashboard.reports.last_run') }}</dt>
                <dd>
                    @if ($schedule->last_run_at)
                        <x-ui.local-time :value="$schedule->last_run_at" format="smart" />
                    @else
                        {{ __('dashboard.reports.never') }}
                    @endif
                </dd>
            </dl>

            @if ($canManage)
                <div class="flex shrink-0 items-center gap-1">
                    <x-admin.tooltip :text="__('dashboard.reports.actions.run_now')">
                        <x-ui.button variant="ghost" size="icon" class="size-8" wire:click="runSchedule({{ $schedule->id }})" aria-label="{{ __('dashboard.reports.actions.run_now') }}">
                            <x-lucide-play class="size-4" />
                        </x-ui.button>
                    </x-admin.tooltip>
                    <x-admin.tooltip :text="__('common.edit')">
                        <x-ui.button variant="ghost" size="icon" class="size-8" wire:click="openSchedule({{ $schedule->id }})" aria-label="{{ __('common.edit') }}">
                            <x-lucide-pencil class="size-4" />
                        </x-ui.button>
                    </x-admin.tooltip>
                    <x-admin.tooltip :text="$schedule->is_active ? __('dashboard.reports.actions.pause') : __('dashboard.reports.actions.resume')">
                        <x-ui.button variant="ghost" size="icon" class="size-8" wire:click="toggleSchedule({{ $schedule->id }})"
                            aria-label="{{ $schedule->is_active ? __('dashboard.reports.actions.pause') : __('dashboard.reports.actions.resume') }}">
                            <x-dynamic-component :component="$schedule->is_active ? 'lucide-pause' : 'lucide-circle-play'" class="size-4" />
                        </x-ui.button>
                    </x-admin.tooltip>
                    <x-admin.tooltip :text="__('common.delete')">
                        <x-ui.button variant="ghost" size="icon" class="size-8 text-destructive hover:text-destructive" wire:click="confirmDeleteSchedule({{ $schedule->id }})" aria-label="{{ __('common.delete') }}">
                            <x-lucide-trash-2 class="size-4" />
                        </x-ui.button>
                    </x-admin.tooltip>
                </div>
            @endif
        </x-ui.card>
    @empty
        <x-ui.empty class="border">
            <x-ui.empty-header>
                <x-ui.empty-media variant="icon"><x-lucide-calendar-clock /></x-ui.empty-media>
                <x-ui.empty-title>{{ __('dashboard.reports.empty.scheduled_title') }}</x-ui.empty-title>
                <x-ui.empty-description>{{ __('dashboard.reports.empty.scheduled_body') }}</x-ui.empty-description>
            </x-ui.empty-header>
            @if ($canManage && count($definitions))
                <x-ui.button size="sm" wire:click="openSchedule">
                    <x-lucide-calendar-plus class="size-4" />
                    {{ __('dashboard.reports.actions.schedule') }}
                </x-ui.button>
            @endif
        </x-ui.empty>
    @endforelse
</div>
