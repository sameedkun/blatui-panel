{{-- Generated reports list. Polls while anything is still queued/processing. --}}
@php
    use App\Enum\ReportStatus;

    $statusTones = [
        ReportStatus::Pending->value => 'neutral',
        ReportStatus::Processing->value => 'info',
        ReportStatus::Completed->value => 'success',
        ReportStatus::Failed->value => 'danger',
    ];
    $canDelete = auth()->user()->can('dashboard.reports.delete');
    $canCreate = auth()->user()->can('dashboard.reports.create');
@endphp

<div class="flex flex-col gap-4" @if ($hasInFlight) wire:poll.4s @endif>

    {{-- Source filter --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach (\App\Livewire\Admin\Dashboard\Reports::SOURCES as $option)
            <button type="button" wire:click="setSource('{{ $option }}')" @class([
                'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                'border-foreground bg-foreground text-background' => $source === $option,
                'border-border text-muted-foreground hover:text-foreground' => $source !== $option,
            ])>{{ __('dashboard.reports.sources.'.$option) }}</button>
        @endforeach

        @if ($hasInFlight)
            <span class="ml-auto flex items-center gap-1.5 text-xs text-muted-foreground">
                <x-ui.spinner class="size-3.5" />
                {{ __('dashboard.reports.working') }}
            </span>
        @endif
    </div>

    <div class="overflow-x-auto rounded-md border border-border">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-border bg-muted/40">
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('dashboard.reports.columns.report') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('dashboard.reports.columns.period') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground md:table-cell">{{ __('dashboard.reports.columns.format') }}</th>
                    <th class="px-4 py-3 text-left font-medium text-foreground">{{ __('dashboard.reports.columns.status') }}</th>
                    <th class="hidden px-4 py-3 text-right font-medium text-foreground lg:table-cell">{{ __('dashboard.reports.columns.rows') }}</th>
                    <th class="hidden px-4 py-3 text-left font-medium text-foreground xl:table-cell">{{ __('dashboard.reports.columns.requested') }}</th>
                    <th class="w-10 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($reports as $report)
                    @php $definition = $definitions[$report->report] ?? null; @endphp
                    <tr wire:key="report-row-{{ $report->id }}" class="hover:bg-muted/30">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                    <x-dynamic-component :component="'lucide-'.($definition?->icon() ?? 'file-text')" class="size-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">{{ $report->title }}</p>
                                    <p class="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                        @if ($report->source === \App\Enum\ReportSource::Scheduled)
                                            <x-lucide-calendar-clock class="size-3" />
                                        @endif
                                        {{ $definition?->label() }}
                                        @if ($report->filters)
                                            · {{ collect($definition?->describeFilters($report->filters) ?? [])->map(fn ($value, $label) => $label.': '.$value)->implode(', ') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-muted-foreground">{{ $report->range()->label() }}</td>
                        <td class="hidden px-4 py-3 md:table-cell">
                            <x-ui.badge variant="outline" class="font-mono uppercase">{{ $report->format->value }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <x-ui.badge :tone="$statusTones[$report->status->value]">
                                    @if ($report->status->isInFlight())
                                        <x-ui.spinner class="size-3" />
                                    @endif
                                    {{ $report->status->label() }}
                                </x-ui.badge>
                                @if ($report->status === ReportStatus::Failed && $report->error)
                                    <x-admin.tooltip :text="$report->error">
                                        <x-lucide-info class="size-3.5 text-muted-foreground" />
                                    </x-admin.tooltip>
                                @endif
                            </div>
                        </td>
                        <td class="hidden px-4 py-3 text-right tabular-nums text-muted-foreground lg:table-cell">
                            @if ($report->row_count !== null)
                                {{ number_format($report->row_count) }}
                                <span class="block text-xs">{{ \Illuminate\Support\Number::fileSize((int) $report->file_size, precision: 1) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="hidden px-4 py-3 xl:table-cell">
                            <p class="truncate text-sm">{{ $report->requester?->name ?? ($report->source === \App\Enum\ReportSource::Scheduled ? __('dashboard.reports.schedule') : '—') }}</p>
                            <x-ui.local-time :value="$report->created_at" format="smart" class="text-xs text-muted-foreground" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if ($report->isDownloadable())
                                    <x-admin.tooltip :text="__('dashboard.reports.actions.download')">
                                        <x-ui.button variant="ghost" size="icon" class="size-8" wire:click="download({{ $report->id }})" aria-label="{{ __('dashboard.reports.actions.download') }}">
                                            <x-lucide-download class="size-4" />
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endif
                                @if ($report->status === ReportStatus::Failed && $canCreate)
                                    <x-admin.tooltip :text="__('dashboard.reports.actions.retry')">
                                        <x-ui.button variant="ghost" size="icon" class="size-8" wire:click="retry({{ $report->id }})" aria-label="{{ __('dashboard.reports.actions.retry') }}">
                                            <x-lucide-rotate-ccw class="size-4" />
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endif
                                @if ($canDelete && ! $report->status->isInFlight())
                                    <x-admin.tooltip :text="__('common.delete')">
                                        <x-ui.button variant="ghost" size="icon" class="size-8 text-destructive hover:text-destructive" wire:click="confirmDelete({{ $report->id }})" aria-label="{{ __('common.delete') }}">
                                            <x-lucide-trash-2 class="size-4" />
                                        </x-ui.button>
                                    </x-admin.tooltip>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-14 text-center">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <x-lucide-file-chart-column class="size-8 opacity-40" />
                                <p class="text-sm font-medium text-foreground">{{ __('dashboard.reports.empty.generated_title') }}</p>
                                <p class="text-xs">{{ __('dashboard.reports.empty.generated_body') }}</p>
                                @if ($canCreate && count($definitions))
                                    <x-ui.button size="sm" class="mt-2" wire:click="openGenerate">
                                        <x-lucide-plus class="size-4" />
                                        {{ __('dashboard.reports.actions.generate') }}
                                    </x-ui.button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-admin.pagination :paginator="$reports" />
</div>
