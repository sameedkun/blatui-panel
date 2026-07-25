{{--
    Per-record audit trail for a plan. Reads straight off Spatie's
    `forSubject()` scope; display data comes from {@see \App\Support\ActivityPresenter}.
    Clicking a row opens the full-detail dialog in place (via
    {@see \App\Livewire\Admin\Concerns\HasActivityDetailModal}) — the same
    dialog the full Activity Logs viewer uses.

    Expects: $record, $activities (paginated Activity, ->with('causer')), $selectedActivity
--}}
@php
    use App\Support\ActivityPresenter;
@endphp

<x-ui.card>
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm font-medium">Activity</p>
        @can('activity_logs.view')
            <a href="{{ route('admin.activity-logs.index', ['subjectType' => $record->getMorphClass(), 'subjectId' => $record->id]) }}"
                class="text-xs text-primary underline hover:no-underline">
                View full history
            </a>
        @endcan
    </div>

    <div class="divide-y divide-border">
        @forelse ($activities as $activity)
            @php $item = ActivityPresenter::present($activity, $record); @endphp
            <button type="button" wire:click="viewActivityDetail({{ $activity->id }})"
                wire:key="plan-activity-{{ $activity->id }}"
                class="group flex w-full gap-3 py-4 text-left first:pt-0 last:pb-0 hover:bg-muted/30">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $item['colorClass'] }}">
                    <x-dynamic-component :component="'lucide-' . $item['icon']" class="size-4" />
                </span>

                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                        <p class="text-sm font-medium">{{ $item['title'] }}</p>
                        <span class="flex shrink-0 items-center gap-1.5">
                            <x-ui.local-time :value="$item['timestamp']" format="smart" class="text-xs text-muted-foreground" />
                            <x-lucide-chevron-right class="size-4 text-muted-foreground/40 group-hover:text-muted-foreground" />
                        </span>
                    </div>

                    @if ($item['rows'])
                        <dl class="flex flex-col gap-1">
                            @foreach ($item['rows'] as $row)
                                <div class="flex items-start gap-2 text-sm">
                                    <dt class="w-24 shrink-0 text-muted-foreground">{{ $row['label'] }}</dt>
                                    <dd class="min-w-0 flex-1 break-words">
                                        @if (is_array($row['value']))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($row['value'] as $chip)
                                                    <x-ui.badge variant="outline" class="font-normal">{{ $chip }}</x-ui.badge>
                                                @endforeach
                                            </div>
                                        @else
                                            {{ $row['value'] }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            </button>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
                <x-lucide-clipboard-list class="size-8 text-muted-foreground/30" />
                <p class="text-sm text-muted-foreground">No activity recorded for this plan yet.</p>
            </div>
        @endforelse
    </div>

    @if ($activities->hasPages())
        <div class="mt-4 border-t border-border pt-4">
            {{ $activities->links('livewire.admin.partials.pagination') }}
        </div>
    @endif

    {{-- In-place detail dialog — same partial the Activity Logs viewer uses --}}
    @include('livewire.admin.administration.activity-logs.partials.detail-dialog', [
        'activity' => $selectedActivity,
        'dialogId' => 'record-activity-detail',
        'closeMethod' => 'clearSelectedActivityDetail',
        'showScopeLink' => false,
        'currentRecord' => $record,
    ])
</x-ui.card>
