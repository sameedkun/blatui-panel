<?php

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\DateRange;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class DateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Date::parse('2026-09-28 14:30:00'));
    }

    public function test_day_presets_end_now_and_include_today_as_their_last_bucket(): void
    {
        $range = DateRange::preset('7d');

        $this->assertSame('2026-09-22 00:00:00', $range->start->toDateTimeString());
        $this->assertSame('2026-09-28 14:30:00', $range->end->toDateTimeString());
        $this->assertSame(7, $range->days());
        $this->assertSame('day', $range->granularity());
        $this->assertCount(7, $range->buckets());
        $this->assertSame('2026-09-28', array_key_last($range->buckets()));
    }

    public function test_the_twelve_month_preset_is_bucketed_by_month(): void
    {
        $range = DateRange::preset('12m');

        $this->assertSame('2025-10-01 00:00:00', $range->start->toDateTimeString());
        $this->assertSame('month', $range->granularity());
        $this->assertSame(['2025-10', '2026-09'], [array_key_first($range->buckets()), array_key_last($range->buckets())]);
        $this->assertCount(12, $range->buckets());
    }

    public function test_unknown_preset_keys_fall_back_to_the_default(): void
    {
        $this->assertSame(DateRange::DEFAULT, DateRange::preset('forever')->key);
        $this->assertSame(DateRange::DEFAULT, DateRange::preset(null)->key);
        $this->assertFalse(DateRange::isPreset('forever'));
        $this->assertTrue(DateRange::isPreset('90d'));
    }

    public function test_previous_is_the_equally_long_window_immediately_before(): void
    {
        $range = DateRange::preset('30d');
        $previous = $range->previous();

        $this->assertSame($range->start->subSecond()->toDateTimeString(), $previous->end->toDateTimeString());
        $this->assertSame(
            (int) $range->start->diffInSeconds($range->end),
            (int) $previous->start->diffInSeconds($range->start),
        );
    }

    public function test_between_covers_whole_days_and_orders_its_bounds(): void
    {
        $range = DateRange::between(Date::parse('2026-09-30 10:00'), Date::parse('2026-09-01 18:00'));

        $this->assertSame(DateRange::CUSTOM, $range->key);
        $this->assertSame('2026-09-01 00:00:00', $range->start->toDateTimeString());
        $this->assertSame('2026-09-30 23:59:59', $range->end->toDateTimeString());
        $this->assertSame(30, $range->days());
        $this->assertSame('custom:20260901-20260930', $range->cacheKey());
    }

    public function test_long_custom_windows_switch_to_monthly_buckets(): void
    {
        $this->assertSame('day', DateRange::between(Date::parse('2026-01-01'), Date::parse('2026-04-01'))->granularity());
        $this->assertSame('month', DateRange::between(Date::parse('2026-01-01'), Date::parse('2026-06-30'))->granularity());
    }
}
