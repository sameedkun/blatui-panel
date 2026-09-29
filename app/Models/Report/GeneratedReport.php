<?php

namespace App\Models\Report;

use App\Enum\ReportFormat;
use App\Enum\ReportSource;
use App\Enum\ReportStatus;
use App\Models\User;
use App\Services\Report\ReportService;
use App\Support\Dashboard\DateRange;
use Database\Factories\Report\GeneratedReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One generated report file: what was asked for (report, period, filters,
 * format), where it is in the queue, and — once complete — where the file
 * lives on the default disk. Rows and files are only ever created/removed
 * through {@see ReportService}.
 */
class GeneratedReport extends Model
{
    /** @use HasFactory<GeneratedReportFactory> */
    use HasFactory;

    protected $fillable = [
        'report',
        'title',
        'format',
        'status',
        'source',
        'range_start',
        'range_end',
        'filters',
        'file_path',
        'file_size',
        'row_count',
        'error',
        'requested_by',
        'scheduled_report_id',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (GeneratedReport $report): void {
            $report->ulid ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'format' => ReportFormat::class,
            'status' => ReportStatus::class,
            'source' => ReportSource::class,
            'filters' => 'array',
            'range_start' => 'datetime',
            'range_end' => 'datetime',
            'file_size' => 'integer',
            'row_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<ScheduledReport, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class, 'scheduled_report_id');
    }

    public function range(): DateRange
    {
        return DateRange::between($this->range_start, $this->range_end);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ReportStatus::Completed && $this->file_path !== null;
    }

    /** "monthly-revenue_2026-09-01_2026-09-30.pdf" — what the browser saves it as. */
    public function downloadName(): string
    {
        return sprintf(
            '%s_%s_%s.%s',
            Str::slug($this->title),
            $this->range_start->format('Y-m-d'),
            $this->range_end->format('Y-m-d'),
            $this->format->extension(),
        );
    }

    /** @param  Builder<GeneratedReport>  $query */
    public function scopeInFlight(Builder $query): Builder
    {
        return $query->whereIn('status', [ReportStatus::Pending->value, ReportStatus::Processing->value]);
    }
}
