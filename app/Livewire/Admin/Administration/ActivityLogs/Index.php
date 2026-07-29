<?php

namespace App\Livewire\Admin\Administration\ActivityLogs;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use App\Jobs\ExportActivityLog;
use App\Livewire\Admin\BaseIndex;
use App\Models\User;
use App\Support\ActivityLogQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only viewer for the four-axis audit log. Audit entries are immutable —
 * this page never creates, edits, or deletes them; it only reads, filters,
 * inspects, and exports.
 *
 * Every filter is #[Url]-bound so a filtered view (and an open detail modal) is
 * shareable and survives a refresh. Filtering on module/context queries the
 * canonical JSON store (properties->module / properties->context); the axis
 * dropdowns are populated straight from their enums.
 */
#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    public string $sortBy = 'created_at';

    public string $sortDir = 'desc';

    /** @var array<string, mixed> */
    #[Url]
    public array $filters = [
        'module' => [],
        'action' => [],
        'context' => [],
        'category' => [],
        'causer' => [],
        'range' => '',
        'date_from' => '',
        'date_to' => '',
    ];

    #[Url]
    public string $search = '';

    /** Deep-linked detail modal — the id of the activity being inspected. */
    #[Url]
    public ?int $selectedId = null;

    /** Deep-linked "all activity for this record" scope (Spatie forSubject). */
    #[Url]
    public ?string $subjectType = null;

    #[Url]
    public ?int $subjectId = null;

    /**
     * Required by {@see BaseIndex}; the real query is assembled by
     * {@see filteredQuery()} via the shared {@see ActivityLogQuery} builder, so
     * this simply exposes the unfiltered base.
     */
    protected function baseQuery(): Builder
    {
        return Activity::query();
    }

    /** The filtered (but unpaginated, unsorted) query — shared by list, stats, and export. */
    protected function filteredQuery(): Builder
    {
        return ActivityLogQuery::build($this->exportState())->with('causer');
    }

    // ── Detail modal ──────────────────────────────────────────────────────────

    public function viewActivity(int $id): void
    {
        $this->selectedId = $id;
        $this->dispatch('open-dialog-activity-detail');
    }

    /** Clear the deep-linked selection (fired when the modal closes). */
    public function clearSelected(): void
    {
        $this->selectedId = null;
    }

    public function clearSubject(): void
    {
        $this->subjectType = null;
        $this->subjectId = null;
        $this->resetPage();
    }

    /** Scope the list to the subject of a given activity (derives the class server-side). */
    public function scopeToActivitySubject(int $activityId): void
    {
        $activity = Activity::find($activityId);

        if ($activity === null || $activity->subject_type === null) {
            return;
        }

        $this->subjectType = $activity->subject_type;
        $this->subjectId = $activity->subject_id;
        $this->selectedId = null;
        $this->resetPage();
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /**
     * Export the current filtered set. Small sets stream straight to the browser;
     * anything over the threshold is handed to a queued job so the request
     * doesn't hang building a huge file.
     */
    public function export(): ?StreamedResponse
    {
        $this->authorize('activity_logs.export');

        $query = $this->filteredQuery();
        $threshold = (int) config('panel.activity_log_export_queue_threshold', 5000);

        if ($query->clone()->count() > $threshold) {
            ExportActivityLog::dispatch($this->exportState(), auth()->id(), app()->getLocale());
            $this->toastSuccess(__('activity_logs.messages.export_queued'));

            return null;
        }

        $filename = 'activity-log-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, __('activity_logs.export.headers'));

            $query->with('causer')->orderBy('id', 'desc')->chunk(500, function ($activities) use ($handle): void {
                foreach ($activities as $activity) {
                    fputcsv($handle, $this->exportRow($activity));
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<int, string> */
    protected function exportRow(Activity $activity): array
    {
        $properties = $activity->properties;

        return [
            $activity->id,
            $activity->created_at?->toDateTimeString() ?? '',
            $activity->log_name ?? '',
            $properties['module'] ?? '',
            $activity->event ?? '',
            $properties['context'] ?? '',
            $activity->causer?->name ?? '',
            $activity->subject_type ? class_basename($activity->subject_type).'#'.$activity->subject_id : '',
            $activity->description,
            $properties->toJson(),
        ];
    }

    /**
     * A serializable snapshot of the active filter state for the queued export.
     *
     * @return array<string, mixed>
     */
    protected function exportState(): array
    {
        return [
            'filters' => $this->filters,
            'search' => $this->search,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
        ];
    }

    // ── Enum-driven option lists ──────────────────────────────────────────────

    /** @return array<string, string> */
    protected function moduleOptions(): array
    {
        return collect(ActivityModule::cases())
            ->mapWithKeys(fn (ActivityModule $case): array => [$case->value => $case->label()])
            ->all();
    }

    /** @return array<string, string> */
    protected function actionOptions(): array
    {
        return collect(ActivityAction::cases())
            ->mapWithKeys(fn (ActivityAction $case): array => [$case->value => $case->label()])
            ->all();
    }

    /** @return array<string, string> */
    protected function contextOptions(): array
    {
        return collect(ActivityContext::cases())
            ->mapWithKeys(fn (ActivityContext $case): array => [$case->value => $case->label()])
            ->all();
    }

    /** @return array<string, string> */
    protected function categoryOptions(): array
    {
        return collect(ActivityLogName::cases())
            ->mapWithKeys(fn (ActivityLogName $case): array => [$case->value => $case->label()])
            ->all();
    }

    /** Causer filter options: real admins that appear in the log, plus a System sentinel for null-causer (system) events. */
    protected function causerOptions(): array
    {
        $ids = Activity::query()
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        $admins = User::whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        // Only offer System if there are actually null-causer events to filter to
        $hasSystem = Activity::query()->whereNull('causer_id')->exists();

        return $hasSystem
            ? ['system' => __('activity_logs.values.system')] + $admins   // sentinel first
            : $admins;
    }

    protected function rangeOptions(): array
    {
        return [
            'today' => __('activity_logs.ranges.today'),
            '7d' => __('activity_logs.ranges.last_7_days'),
            '30d' => __('activity_logs.ranges.last_30_days'),
        ];
    }

    // ── Filter bar UI config ──────────────────────────────────────────────────

    protected function filterBarConfig(): array
    {
        return [
            'range' => [
                'label' => __('activity_logs.filters.period'),
                'type' => 'select',
                'options' => $this->rangeOptions(),
            ],
            'module' => [
                'label' => __('activity_logs.filters.module'),
                'type' => 'multi-select',
                'options' => $this->moduleOptions(),
            ],
            'action' => [
                'label' => __('activity_logs.filters.action'),
                'type' => 'multi-select',
                'options' => $this->actionOptions(),
            ],
            'context' => [
                'label' => __('activity_logs.filters.context'),
                'type' => 'multi-select',
                'options' => $this->contextOptions(),
            ],
            'category' => [
                'label' => __('activity_logs.filters.category'),
                'type' => 'multi-select',
                'options' => $this->categoryOptions(),
            ],
            'causer' => [
                'label' => __('activity_logs.filters.causer'),
                'type' => 'multi-select',
                'options' => $this->causerOptions(),
            ],
            'custom' => [
                'label' => __('activity_logs.filters.custom_dates'),
                'type' => 'date-range',
                'from_key' => 'date_from',
                'to_key' => 'date_to',
            ],
        ];
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    /**
     * Security signals lead (24h), then the activity pulse. All are computed off
     * fixed rolling windows rather than the filter state so they stay meaningful
     * regardless of how the list below is scoped.
     *
     * @return array<string, mixed>
     */
    protected function securitySignals(): array
    {
        $since = now()->subDay();

        return [
            'failed_logins' => Activity::where('event', ActivityAction::Failed->value)
                ->where('created_at', '>=', $since)
                ->count(),
            'destructive' => Activity::where('created_at', '>=', $since)
                ->where(function (Builder $q): void {
                    $q->whereIn('event', [
                        ActivityAction::Banned->value,
                        ActivityAction::ForceDeleted->value,
                        ActivityAction::Purged->value,
                        ActivityAction::Deleted->value,
                    ])->orWhere(function (Builder $sub): void {
                        $sub->where('event', ActivityAction::Updated->value)
                            ->whereIn('properties->module', [
                                ActivityModule::Role->value,
                                ActivityModule::Permission->value,
                            ]);
                    });
                })
                ->count(),
            'active_causers' => Activity::where('created_at', '>=', $since)
                ->whereNotNull('causer_id')
                ->distinct()
                ->count('causer_id'),
        ];
    }

    /** @return array{today: int, week: int, month: int} */
    protected function activityPulse(): array
    {
        return [
            'today' => Activity::where('created_at', '>=', now()->startOfDay())->count(),
            'week' => Activity::where('created_at', '>=', now()->startOfWeek())->count(),
            'month' => Activity::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /**
     * Events grouped by module for the currently filtered range, largest first —
     * powers the compact breakdown bars.
     *
     * @return array<int, array{label: string, value: string, count: int}>
     */
    protected function moduleBreakdown(): array
    {
        // One GROUP BY on the JSON-extracted module key, rather than a COUNT per
        // module. MySQL's json_extract returns the value quoted ("user"), so the
        // keys are unquoted before mapping enum labels over them.
        $counts = $this->filteredQuery()
            ->select('properties->module as module_key')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('properties->module')
            ->pluck('aggregate', 'module_key')
            ->mapWithKeys(fn ($aggregate, $key): array => [trim((string) $key, '"') => (int) $aggregate]);

        return collect($this->moduleOptions())
            ->map(fn (string $label, string $value): array => [
                'label' => $label,
                'value' => $value,
                'count' => $counts->get($value, 0),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $activities = $this->filteredQuery()
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);

        $selectedActivity = $this->selectedId !== null
            ? Activity::with(['causer', 'subject'])->find($this->selectedId)
            : null;

        $breakdown = $this->moduleBreakdown();

        return view('livewire.admin.administration.activity-logs.index', [
            'activities' => $activities,
            'selectedActivity' => $selectedActivity,
            'filterBarConfig' => $this->filterBarConfig(),
            'signals' => $this->securitySignals(),
            'pulse' => $this->activityPulse(),
            'breakdown' => $breakdown,
            'breakdownTotal' => array_sum(array_column($breakdown, 'count')),
        ])->title(__('activity_logs.title'));
    }
}
