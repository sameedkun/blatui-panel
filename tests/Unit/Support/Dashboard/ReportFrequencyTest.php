<?php

namespace Tests\Unit\Support\Dashboard;

use App\Enum\ReportFrequency;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ReportFrequencyTest extends TestCase
{
    public function test_each_run_covers_the_last_complete_period(): void
    {
        $runAt = Date::parse('2026-09-28 08:00'); // a Monday

        $daily = ReportFrequency::Daily->periodBefore($runAt);
        $weekly = ReportFrequency::Weekly->periodBefore($runAt);
        $monthly = ReportFrequency::Monthly->periodBefore($runAt);

        $this->assertSame(['2026-09-27 00:00:00', '2026-09-27 23:59:59'], [$daily->start->toDateTimeString(), $daily->end->toDateTimeString()]);
        $this->assertSame(['2026-09-21 00:00:00', '2026-09-27 23:59:59'], [$weekly->start->toDateTimeString(), $weekly->end->toDateTimeString()]);
        $this->assertSame(['2026-08-01 00:00:00', '2026-08-31 23:59:59'], [$monthly->start->toDateTimeString(), $monthly->end->toDateTimeString()]);
    }

    public function test_next_run_is_strictly_after_the_given_moment(): void
    {
        $at = Date::parse('2026-09-28 09:30'); // Monday

        $this->assertSame('2026-09-29 08:00', ReportFrequency::Daily->nextRunAfter($at, 8)->format('Y-m-d H:i'));
        $this->assertSame('2026-09-28 10:00', ReportFrequency::Daily->nextRunAfter($at, 10)->format('Y-m-d H:i'));

        // Weekly on Monday at 08:00 already passed today → next Monday.
        $this->assertSame('2026-10-05 08:00', ReportFrequency::Weekly->nextRunAfter($at, 8, dayOfWeek: 1)->format('Y-m-d H:i'));
        // Weekly on Sunday → the coming Sunday.
        $this->assertSame('2026-10-04 08:00', ReportFrequency::Weekly->nextRunAfter($at, 8, dayOfWeek: 0)->format('Y-m-d H:i'));

        $this->assertSame('2026-10-01 08:00', ReportFrequency::Monthly->nextRunAfter($at, 8, dayOfMonth: 1)->format('Y-m-d H:i'));
        $this->assertSame('2026-09-28 12:00', ReportFrequency::Monthly->nextRunAfter($at, 12, dayOfMonth: 28)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-28 08:00', ReportFrequency::Monthly->nextRunAfter($at, 8, dayOfMonth: 28)->format('Y-m-d H:i'));
    }
}
