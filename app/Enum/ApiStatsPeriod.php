<?php

namespace App\Enum;

use App\Models\ApiLog\ApiRequestStat;
use Carbon\CarbonInterface;

/**
 * Granularity of an {@see ApiRequestStat} rollup row. Hour rows are built from
 * raw request logs, day rows from hour rows, month rows from day rows.
 */
enum ApiStatsPeriod: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Month = 'month';

    public function label(): string
    {
        return __("enums.api_stats_period.{$this->name}");
    }

    /** The start of the period containing $date. */
    public function floor(CarbonInterface $date): CarbonInterface
    {
        return match ($this) {
            self::Hour => $date->startOfHour(),
            self::Day => $date->startOfDay(),
            self::Month => $date->startOfMonth(),
        };
    }

    /** The start of the period following the one that starts at $bucket. */
    public function next(CarbonInterface $bucket): CarbonInterface
    {
        return match ($this) {
            self::Hour => $bucket->addHour(),
            self::Day => $bucket->addDay(),
            self::Month => $bucket->addMonthNoOverflow(),
        };
    }

    /** The finer period this one is rolled up from, or null for Hour (built from raw logs). */
    public function source(): ?self
    {
        return match ($this) {
            self::Hour => null,
            self::Day => self::Hour,
            self::Month => self::Day,
        };
    }
}
