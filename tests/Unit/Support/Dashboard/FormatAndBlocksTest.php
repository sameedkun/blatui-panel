<?php

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\Blocks\BarList;
use App\Support\Dashboard\Blocks\Chart;
use App\Support\Dashboard\Blocks\Funnel;
use App\Support\Dashboard\Blocks\HealthIndicator;
use App\Support\Dashboard\Blocks\Metric;
use App\Support\Dashboard\Blocks\Row;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\TimeSeries;
use Tests\TestCase;

class FormatAndBlocksTest extends TestCase
{
    public function test_change_is_undefined_without_a_baseline(): void
    {
        $this->assertSame(25.0, Format::change(125, 100));
        $this->assertSame(-50.0, Format::change(50, 100));
        $this->assertNull(Format::change(10, 0));
        $this->assertNull(Format::change(10, null));
    }

    public function test_values_are_formatted_by_format_name(): void
    {
        $this->assertSame('12,842', Format::value(12842));
        $this->assertSame('12.5%', Format::value(12.5, Format::PERCENT));
        $this->assertSame('40%', Format::value(40.0, Format::PERCENT));
        $this->assertSame('2h 14m', Format::value(134, Format::DURATION));
        $this->assertSame('3d 4h', Format::value(3 * 1440 + 240, Format::DURATION));
        $this->assertSame('—', Format::value(null, Format::CURRENCY));
        $this->assertStringContainsString('24,820.00', Format::currency(24820));
    }

    public function test_metric_sentiment_follows_meaning_not_direction(): void
    {
        $growth = Metric::make('Users', 120)->compareTo(100);
        $tickets = Metric::make('Open tickets', 120)->compareTo(100)->invert();

        $this->assertSame('up', $growth->direction());
        $this->assertSame('positive', $growth->sentiment());
        $this->assertSame('up', $tickets->direction());
        $this->assertSame('negative', $tickets->sentiment());
        $this->assertSame('neutral', Metric::make('New', 5)->sentiment());
    }

    public function test_chart_options_never_emit_a_null_formatter(): void
    {
        $chart = Chart::make('Revenue')->labels(['a', 'b'])->series('x', [1, 2])->series('y', [null, 3])->dashed(1);

        $this->assertStringNotContainsString('formatter', json_encode($chart->options()));
        $this->assertSame([0, 5], $chart->options()['stroke']['dashArray']);
        $this->assertFalse($chart->isEmpty());
        $this->assertTrue(Chart::make('Empty')->series('x', [0, 0])->isEmpty());
        $this->assertTrue(Chart::make('Donut', Chart::DONUT)->slices(['a' => 0])->isEmpty());
    }

    public function test_bar_list_and_funnel_derive_shares(): void
    {
        $list = BarList::make('Plans')->items(['Basic' => 10, 'Pro' => 30])->sortDescending();

        $this->assertSame('Pro', $list->items[0]['label']);
        $this->assertSame(40.0, $list->total());
        $this->assertSame(30.0, $list->max());

        $funnel = Funnel::make('Trial')->step('Started', 200)->step('Converted', 50);

        $this->assertNull($funnel->conversionAt(0));
        $this->assertSame(25.0, $funnel->conversionAt(1));
        $this->assertNull($funnel->withoutConversion()->conversionAt(1));
    }

    public function test_health_grades_against_thresholds(): void
    {
        $this->assertSame(HealthIndicator::OK, HealthIndicator::grade(0.5, 1, 5));
        $this->assertSame(HealthIndicator::WARNING, HealthIndicator::grade(1, 1, 5));
        $this->assertSame(HealthIndicator::CRITICAL, HealthIndicator::grade(9, 1, 5));
        $this->assertSame(HealthIndicator::UNKNOWN, HealthIndicator::grade(null, 1, 5));
    }

    public function test_rows_clamp_their_width_and_spans(): void
    {
        $row = Row::of(Metric::make('a', 1), Metric::make('b', 2)->span(9));

        $this->assertSame(4, $row->columns);
        $this->assertSame(4, $row->blocks[1]->span);
        $this->assertTrue($row->isMetricStrip());
        $this->assertFalse(Row::columns(2, Metric::make('a', 1), BarList::make('b'))->isMetricStrip());
    }

    public function test_previous_period_series_are_aligned_to_the_current_axis(): void
    {
        $this->assertSame([2, 3], TimeSeries::alignPrevious(['a' => 1, 'b' => 2, 'c' => 3], 2));
        $this->assertSame([null, 1, 2], TimeSeries::alignPrevious(['a' => 1, 'b' => 2], 3));
        $this->assertSame(['a' => 5, 'b' => 7], TimeSeries::cumulative(['a' => 2, 'b' => 2], 3));
    }
}
