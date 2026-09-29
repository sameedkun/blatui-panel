<?php

namespace App\Livewire\Admin\Dashboard;

use App\Enum\ReportFormat;
use App\Enum\ReportFrequency;
use App\Enum\ReportSource;
use App\Livewire\Admin\Concerns\HasToast;
use App\Models\Report\GeneratedReport;
use App\Models\Report\ScheduledReport;
use App\Services\Report\ReportService;
use App\Support\Dashboard\DashboardRegistry;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Reports\ReportDefinition;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard → Reports: "give me a defined dataset I can download, share or
 * schedule".
 *
 * Deliberately not another analytics page. It lists generated report files
 * (polling while any are still in the queue), generates new ones on demand,
 * manages recurring schedules, and browses the library of report
 * definitions. All state changes go through {@see ReportService}; every
 * report is only visible to viewers holding its definition's permission.
 */
#[Layout('layouts.admin.app')]
class Reports extends Component
{
    use HasToast, WithPagination;

    public const array TABS = ['generated', 'scheduled', 'library'];

    public const array SOURCES = ['all', 'manual', 'scheduled'];

    public const array PRESETS = ['last_7_days', 'last_30_days', 'this_month', 'last_month', 'this_quarter', 'last_quarter', 'year_to_date', 'last_year'];

    #[Url]
    public string $tab = 'generated';

    #[Url]
    public string $source = 'all';

    public int $perPage = 15;

    // ── Generate form ──────────────────────────────────────────────────────

    public string $reportKey = '';

    public string $from = '';

    public string $to = '';

    /** @var array<string, string> */
    public array $filters = [];

    public string $format = 'pdf';

    // ── Schedule form ──────────────────────────────────────────────────────

    public ?int $editingScheduleId = null;

    public string $scheduleName = '';

    public string $scheduleReport = '';

    public string $scheduleFormat = 'pdf';

    public string $scheduleFrequency = 'monthly';

    public int $scheduleDayOfWeek = 1;

    public int $scheduleDayOfMonth = 1;

    public int $scheduleHour = 8;

    public string $scheduleRecipients = '';

    /** @var array<string, string> */
    public array $scheduleFilters = [];

    // ── Pending confirmations ──────────────────────────────────────────────

    public ?int $deletingReportId = null;

    public ?int $deletingScheduleId = null;

    public function mount(): void
    {
        $this->authorize('dashboard.reports.view');

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'generated';
        }

        if (! in_array($this->source, self::SOURCES, true)) {
            $this->source = 'all';
        }

        // Deep link from the Overview's "Generate report" quick action.
        if (request()->boolean('generate') && auth()->user()->can('dashboard.reports.create')) {
            $this->openGenerate();
        }
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->tab = $tab;
        }
    }

    public function setSource(string $source): void
    {
        if (in_array($source, self::SOURCES, true)) {
            $this->source = $source;
            $this->resetPage();
        }
    }

    // ── Generate ───────────────────────────────────────────────────────────

    public function openGenerate(?string $report = null): void
    {
        $this->authorize('dashboard.reports.create');

        $definitions = $this->definitions();
        $this->resetValidation();
        $this->reportKey = $report !== null && isset($definitions[$report]) ? $report : (string) array_key_first($definitions);
        $this->filters = [];
        $this->format = ReportFormat::Pdf->value;
        $this->applyPreset('last_30_days');

        $this->dispatch('open-dialog-generate-report');
    }

    public function updatedReportKey(): void
    {
        $this->filters = [];
    }

    public function updatedScheduleReport(): void
    {
        $this->scheduleFilters = [];
    }

    /** Fill the date range from a named preset ("Last month", "Year to date", …). */
    public function applyPreset(string $preset): void
    {
        if (! in_array($preset, self::PRESETS, true)) {
            return;
        }

        $today = Date::today();

        [$from, $to] = match ($preset) {
            'last_7_days' => [$today->subDays(6), $today],
            'last_30_days' => [$today->subDays(29), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->firstOfQuarter(), $today],
            'last_quarter' => [$today->subQuarterNoOverflow()->firstOfQuarter(), $today->subQuarterNoOverflow()->lastOfQuarter()],
            'year_to_date' => [$today->startOfYear(), $today],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    public function generate(ReportService $reports): void
    {
        $this->authorize('dashboard.reports.create');

        $definitions = $this->definitions();

        $this->validate([
            'reportKey' => ['required', Rule::in(array_keys($definitions))],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'format' => ['required', Rule::enum(ReportFormat::class)],
            'filters' => ['array'],
        ], attributes: $this->validationAttributes());

        $range = DateRange::between(Date::parse($this->from), Date::parse($this->to));

        if ($range->days() > (int) config('dashboard.reports.max_range_days', 366)) {
            $this->addError('to', __('dashboard.reports.errors.range_too_long', ['days' => config('dashboard.reports.max_range_days', 366)]));

            return;
        }

        $reports->request($definitions[$this->reportKey], $range, $this->filters, ReportFormat::from($this->format), auth()->user());

        $this->dispatch('close-dialog-generate-report');
        $this->tab = 'generated';
        $this->resetPage();
        $this->toastSuccess(__('dashboard.reports.toasts.queued'), __('dashboard.reports.toasts.queued_hint'));
    }

    // ── Generated report actions ───────────────────────────────────────────

    public function download(int $id): ?StreamedResponse
    {
        $report = $this->findReport($id);

        if (! $report->isDownloadable() || ! Storage::exists($report->file_path)) {
            $this->toastError(__('dashboard.reports.toasts.missing_file'));

            return null;
        }

        return Storage::download($report->file_path, $report->downloadName(), ['Content-Type' => $report->format->mimeType()]);
    }

    public function retry(int $id, ReportService $reports): void
    {
        $this->authorize('dashboard.reports.create');

        $reports->retry($this->findReport($id));

        $this->toastSuccess(__('dashboard.reports.toasts.retried'));
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('dashboard.reports.delete');

        $this->deletingReportId = $this->findReport($id)->id;
        $this->dispatch('open-alert-dialog-delete-report');
    }

    public function delete(ReportService $reports): void
    {
        $this->authorize('dashboard.reports.delete');

        if ($this->deletingReportId === null) {
            return;
        }

        $reports->delete($this->findReport($this->deletingReportId), auth()->user());
        $this->deletingReportId = null;

        $this->toastSuccess(__('dashboard.reports.toasts.deleted'));
    }

    // ── Schedules ──────────────────────────────────────────────────────────

    public function openSchedule(?int $id = null, ?string $report = null): void
    {
        $this->authorize('dashboard.reports.manage');
        $this->resetValidation();

        $schedule = $id !== null ? $this->findSchedule($id) : null;
        $definitions = $this->definitions();

        $this->editingScheduleId = $schedule?->id;
        $this->scheduleName = $schedule->name ?? '';
        $this->scheduleReport = $schedule->report ?? ($report !== null && isset($definitions[$report]) ? $report : (string) array_key_first($definitions));
        $this->scheduleFormat = $schedule?->format->value ?? ReportFormat::Pdf->value;
        $this->scheduleFrequency = $schedule?->frequency->value ?? ReportFrequency::Monthly->value;
        $this->scheduleDayOfWeek = $schedule->day_of_week ?? 1;
        $this->scheduleDayOfMonth = $schedule->day_of_month ?? 1;
        $this->scheduleHour = $schedule->hour ?? 8;
        $this->scheduleRecipients = implode(', ', $schedule->recipients ?? [auth()->user()->email]);
        $this->scheduleFilters = $schedule->filters ?? [];

        $this->dispatch('open-dialog-schedule-report');
    }

    public function saveSchedule(ReportService $reports): void
    {
        $this->authorize('dashboard.reports.manage');

        $recipients = $this->recipientList();

        $this->validate([
            'scheduleName' => ['required', 'string', 'max:120'],
            'scheduleReport' => ['required', Rule::in(array_keys($this->definitions()))],
            'scheduleFormat' => ['required', Rule::enum(ReportFormat::class)],
            'scheduleFrequency' => ['required', Rule::enum(ReportFrequency::class)],
            'scheduleDayOfWeek' => ['integer', 'between:0,6'],
            'scheduleDayOfMonth' => ['integer', 'between:1,28'],
            'scheduleHour' => ['integer', 'between:0,23'],
            'scheduleFilters' => ['array'],
        ], attributes: $this->validationAttributes());

        $max = (int) config('dashboard.reports.max_recipients', 10);

        if ($recipients === [] || count($recipients) > $max || collect($recipients)->contains(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $this->addError('scheduleRecipients', __('dashboard.reports.errors.recipients', ['max' => $max]));

            return;
        }

        $reports->saveSchedule([
            'name' => $this->scheduleName,
            'report' => $this->scheduleReport,
            'format' => ReportFormat::from($this->scheduleFormat),
            'filters' => $this->scheduleFilters,
            'frequency' => ReportFrequency::from($this->scheduleFrequency),
            'day_of_week' => $this->scheduleDayOfWeek,
            'day_of_month' => $this->scheduleDayOfMonth,
            'hour' => $this->scheduleHour,
            'recipients' => $recipients,
        ], $this->editingScheduleId !== null ? $this->findSchedule($this->editingScheduleId) : null, auth()->user());

        $this->dispatch('close-dialog-schedule-report');
        $this->tab = 'scheduled';
        $this->toastSuccess(__($this->editingScheduleId !== null ? 'dashboard.reports.toasts.schedule_updated' : 'dashboard.reports.toasts.schedule_created'));
        $this->editingScheduleId = null;
    }

    public function toggleSchedule(int $id, ReportService $reports): void
    {
        $this->authorize('dashboard.reports.manage');

        $schedule = $this->findSchedule($id);
        $reports->toggleSchedule($schedule, auth()->user());

        $this->toastSuccess(__($schedule->is_active ? 'dashboard.reports.toasts.schedule_resumed' : 'dashboard.reports.toasts.schedule_paused'));
    }

    /** Run a schedule now for its last complete period, without moving its cadence. */
    public function runSchedule(int $id, ReportService $reports): void
    {
        $this->authorize('dashboard.reports.manage');

        $reports->runSchedule($this->findSchedule($id), advance: false);

        $this->toastSuccess(__('dashboard.reports.toasts.queued'), __('dashboard.reports.toasts.run_hint'));
    }

    public function confirmDeleteSchedule(int $id): void
    {
        $this->authorize('dashboard.reports.manage');

        $this->deletingScheduleId = $this->findSchedule($id)->id;
        $this->dispatch('open-alert-dialog-delete-schedule');
    }

    public function deleteSchedule(ReportService $reports): void
    {
        $this->authorize('dashboard.reports.manage');

        if ($this->deletingScheduleId === null) {
            return;
        }

        $reports->deleteSchedule($this->findSchedule($this->deletingScheduleId), auth()->user());
        $this->deletingScheduleId = null;

        $this->toastSuccess(__('dashboard.reports.toasts.schedule_deleted'));
    }

    public function render(): View
    {
        $definitions = $this->definitions();
        $keys = array_keys($definitions);

        $reports = GeneratedReport::query()
            ->with(['requester' => fn ($query) => $query->withTrashed(), 'schedule'])
            ->whereIn('report', $keys)
            ->when($this->source !== 'all', fn ($query) => $query->where('source', $this->source === 'scheduled' ? ReportSource::Scheduled : ReportSource::Manual))
            ->latest('id')
            ->paginate($this->perPage);

        $visible = GeneratedReport::query()->whereIn('report', $keys);

        return view('livewire.admin.dashboard.reports', [
            'definitions' => $definitions,
            'reports' => $reports,
            'hasInFlight' => (clone $visible)->inFlight()->exists(),
            'schedules' => $this->tab === 'scheduled'
                ? ScheduledReport::query()->with('creator')->whereIn('report', $keys)->latest('id')->get()
                : collect(),
            'stats' => [
                'total' => (clone $visible)->count(),
                'in_flight' => (clone $visible)->inFlight()->count(),
                'schedules' => ScheduledReport::query()->whereIn('report', $keys)->where('is_active', true)->count(),
                'storage' => (int) (clone $visible)->sum('file_size'),
            ],
            'generateDefinition' => $definitions[$this->reportKey] ?? null,
            'scheduleDefinition' => $definitions[$this->scheduleReport] ?? null,
            'formats' => ReportFormat::cases(),
            'frequencies' => ReportFrequency::cases(),
        ])->title(__('dashboard.reports.title'));
    }

    /** @return array<string, ReportDefinition> the definitions this viewer may use */
    protected function definitions(): array
    {
        return app(DashboardRegistry::class)->reports(auth()->user());
    }

    /** A generated report the viewer may see — never one whose definition they lack permission for. */
    protected function findReport(int $id): GeneratedReport
    {
        return GeneratedReport::query()->whereIn('report', array_keys($this->definitions()))->findOrFail($id);
    }

    protected function findSchedule(int $id): ScheduledReport
    {
        return ScheduledReport::query()->whereIn('report', array_keys($this->definitions()))->findOrFail($id);
    }

    /** @return list<string> */
    protected function recipientList(): array
    {
        return collect(preg_split('/[\s,;]+/', $this->scheduleRecipients) ?: [])
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'reportKey' => __('dashboard.reports.fields.report'),
            'from' => __('dashboard.reports.fields.from'),
            'to' => __('dashboard.reports.fields.to'),
            'format' => __('dashboard.reports.fields.format'),
            'scheduleName' => __('dashboard.reports.fields.name'),
            'scheduleReport' => __('dashboard.reports.fields.report'),
            'scheduleFormat' => __('dashboard.reports.fields.format'),
            'scheduleFrequency' => __('dashboard.reports.fields.frequency'),
            'scheduleDayOfWeek' => __('dashboard.reports.fields.day_of_week'),
            'scheduleDayOfMonth' => __('dashboard.reports.fields.day_of_month'),
            'scheduleHour' => __('dashboard.reports.fields.hour'),
        ];
    }
}
