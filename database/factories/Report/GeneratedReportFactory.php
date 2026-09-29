<?php

namespace Database\Factories\Report;

use App\Enum\ReportFormat;
use App\Enum\ReportSource;
use App\Enum\ReportStatus;
use App\Models\Report\GeneratedReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedReport>
 */
class GeneratedReportFactory extends Factory
{
    protected $model = GeneratedReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report' => 'users',
            'title' => 'User growth',
            'format' => ReportFormat::Csv,
            'status' => ReportStatus::Pending,
            'source' => ReportSource::Manual,
            'range_start' => now()->subDays(29)->startOfDay(),
            'range_end' => now()->endOfDay(),
            'filters' => null,
        ];
    }

    /** A finished report whose file lives at $path on the default disk. */
    public function completed(string $path = 'reports/example.csv', int $rows = 10): static
    {
        return $this->state(fn (): array => [
            'status' => ReportStatus::Completed,
            'file_path' => $path,
            'file_size' => 128,
            'row_count' => $rows,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function failed(string $error = 'Something went wrong.'): static
    {
        return $this->state(fn (): array => [
            'status' => ReportStatus::Failed,
            'error' => $error,
            'completed_at' => now(),
        ]);
    }
}
