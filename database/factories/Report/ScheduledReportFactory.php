<?php

namespace Database\Factories\Report;

use App\Enum\ReportFormat;
use App\Enum\ReportFrequency;
use App\Models\Report\ScheduledReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledReport>
 */
class ScheduledReportFactory extends Factory
{
    protected $model = ScheduledReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Monthly users',
            'report' => 'users',
            'format' => ReportFormat::Csv,
            'filters' => null,
            'frequency' => ReportFrequency::Monthly,
            'day_of_week' => null,
            'day_of_month' => 1,
            'hour' => 8,
            'recipients' => [fake()->safeEmail()],
            'is_active' => true,
            'next_run_at' => now()->addDay(),
        ];
    }

    /** A schedule that should have run already. */
    public function due(): static
    {
        return $this->state(fn (): array => ['next_run_at' => now()->subMinute()]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
