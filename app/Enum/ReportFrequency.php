<?php

namespace App\Enum;

use App\Models\Report\ScheduledReport;
use App\Support\Dashboard\DateRange;
use Carbon\CarbonInterface;

/**
 * How often a {@see ScheduledReport} runs, and which period each run covers.
 * Closed vocabulary.
 *
 * Every run reports on the last *complete* period before it — a daily run
 * covers yesterday, a weekly run the previous Monday–Sunday, a monthly run
 * the previous calendar month — so a report is never built from a period
 * that is still filling up.
 */
enum ReportFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return __("enums.report_frequency.{$this->name}");
    }

    /** The complete period a run at $runAt reports on. */
    public function periodBefore(CarbonInterface $runAt): DateRange
    {
        return match ($this) {
            self::Daily => DateRange::between($runAt->subDay(), $runAt->subDay()),
            self::Weekly => DateRange::between($runAt->subWeek()->startOfWeek(CarbonInterface::MONDAY), $runAt->subWeek()->endOfWeek(CarbonInterface::SUNDAY)),
            self::Monthly => DateRange::between($runAt->subMonthNoOverflow()->startOfMonth(), $runAt->subMonthNoOverflow()->endOfMonth()),
        };
    }

    /**
     * The first run strictly after $after.
     *
     * @param  int|null  $dayOfWeek  0 (Sunday) – 6 (Saturday); weekly only.
     * @param  int|null  $dayOfMonth  1 – 28; monthly only (capped so every month has one).
     */
    public function nextRunAfter(CarbonInterface $after, int $hour, ?int $dayOfWeek = null, ?int $dayOfMonth = null): CarbonInterface
    {
        $candidate = match ($this) {
            self::Daily => $after->setTime($hour, 0),
            self::Weekly => $after->startOfWeek(CarbonInterface::MONDAY)->addDays((($dayOfWeek ?? 1) + 6) % 7)->setTime($hour, 0),
            self::Monthly => $after->startOfMonth()->addDays(max(1, min(28, $dayOfMonth ?? 1)) - 1)->setTime($hour, 0),
        };

        while ($candidate->lessThanOrEqualTo($after)) {
            $candidate = match ($this) {
                self::Daily => $candidate->addDay(),
                self::Weekly => $candidate->addWeek(),
                self::Monthly => $candidate->addMonthNoOverflow(),
            };
        }

        return $candidate;
    }
}
