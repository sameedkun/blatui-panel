<?php

namespace App\Models\Report;

use App\Enum\ReportFormat;
use App\Enum\ReportFrequency;
use App\Jobs\Report\RunScheduledReports;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Report\ScheduledReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;

/**
 * A recurring report: which report, how often, and who receives it. Each run
 * (via {@see RunScheduledReports}) creates a GeneratedReport for the last
 * complete period and emails it to {@see $recipients}.
 */
class ScheduledReport extends Model
{
    /** @use HasFactory<ScheduledReportFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'report',
        'format',
        'filters',
        'frequency',
        'day_of_week',
        'day_of_month',
        'hour',
        'recipients',
        'is_active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'format' => ReportFormat::class,
            'frequency' => ReportFrequency::class,
            'filters' => 'array',
            'recipients' => 'array',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'hour' => 'integer',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<GeneratedReport, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'scheduled_report_id');
    }

    /** @param  Builder<ScheduledReport>  $query */
    public function scopeDue(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $query->where('is_active', true)->where('next_run_at', '<=', $at ?? Date::now());
    }

    /** The first run strictly after $after (now by default). */
    public function nextRunAfter(?CarbonInterface $after = null): CarbonInterface
    {
        return $this->frequency->nextRunAfter($after ?? Date::now(), $this->hour, $this->day_of_week, $this->day_of_month);
    }

    /** "Every Monday at 08:00", "1st of every month at 09:00". */
    public function cadenceLabel(): string
    {
        $time = sprintf('%02d:00', $this->hour);

        return match ($this->frequency) {
            ReportFrequency::Daily => __('dashboard.reports.cadence.daily', ['time' => $time]),
            ReportFrequency::Weekly => __('dashboard.reports.cadence.weekly', [
                'day' => Date::now()->startOfWeek(CarbonInterface::SUNDAY)->addDays($this->day_of_week ?? 1)->translatedFormat('l'),
                'time' => $time,
            ]),
            ReportFrequency::Monthly => __('dashboard.reports.cadence.monthly', [
                'day' => Date::create(2026, 1, $this->day_of_month ?? 1)->translatedFormat('jS'),
                'time' => $time,
            ]),
        };
    }
}
