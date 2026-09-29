<?php

namespace App\Support\Dashboard\Blocks;

use App\Support\Dashboard\Format;

/**
 * A time-series or categorical chart rendered with BlatUI's ApexCharts wrapper.
 *
 * Series are plain `['name' => ..., 'data' => [...]]` arrays aligned with
 * {@see $labels}; a donut takes a flat list of numbers instead. ApexCharts
 * formatters are JS functions and cannot travel through PHP, so a chart's
 * {@see $summary} (the formatted headline shown above it) carries any
 * currency/percent presentation instead.
 *
 * @phpstan-consistent-constructor
 */
class Chart extends Block
{
    public const string AREA = 'area';

    public const string LINE = 'line';

    public const string BAR = 'bar';

    public const string DONUT = 'donut';

    public ?string $description = null;

    public ?string $icon = null;

    /** @var list<string> */
    public array $labels = [];

    /** @var list<array{name: string, data: list<int|float|null>}>|list<int|float> */
    public array $series = [];

    /** @var list<string> */
    public array $colors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

    public bool $stacked = false;

    public int $height = 280;

    /** Formatted headline figure shown in the header (e.g. the period total). */
    public ?string $summary = null;

    /**
     * Series indexes drawn dashed (e.g. the previous-period comparison line).
     *
     * @var list<int>
     */
    public array $dashed = [];

    public function __construct(public string $title, public string $type = self::AREA) {}

    public static function make(string $title, string $type = self::AREA): static
    {
        return new static($title, $type);
    }

    /** @param  array<array-key, string>  $labels  re-indexed, so a keyed bucket map can be passed as-is */
    public function labels(array $labels): static
    {
        $this->labels = array_values($labels);

        return $this;
    }

    /** @param  array<array-key, int|float|null>  $data  re-indexed, so a keyed bucket series can be passed as-is */
    public function series(string $name, array $data): static
    {
        $this->series[] = ['name' => $name, 'data' => array_values($data)];

        return $this;
    }

    /** @param  array<string, int|float>  $slices  label => value, for donuts */
    public function slices(array $slices): static
    {
        $this->labels = array_map('strval', array_keys($slices));
        $this->series = array_values($slices);

        return $this;
    }

    /** @param  list<string>  $colors */
    public function colors(array $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    public function stacked(bool $stacked = true): static
    {
        $this->stacked = $stacked;

        return $this;
    }

    public function height(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function summary(int|float|string|null $value, string $format = Format::NUMBER): static
    {
        $this->summary = $value === null ? null : Format::value($value, $format);

        return $this;
    }

    public function dashed(int ...$seriesIndexes): static
    {
        $this->dashed = array_values($seriesIndexes);

        return $this;
    }

    public function isEmpty(): bool
    {
        if ($this->type === self::DONUT) {
            return array_sum(array_map('floatval', $this->series)) <= 0;
        }

        foreach ($this->series as $series) {
            foreach ($series['data'] as $value) {
                if ((float) $value !== 0.0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * ApexCharts options for this chart. Never emits a null `formatter` —
     * ApexCharts invokes formatters unconditionally and throws on null.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        if ($this->type === self::DONUT) {
            return [
                'legend' => ['show' => true, 'position' => 'bottom'],
                'dataLabels' => ['enabled' => false],
                'stroke' => ['width' => 0],
                'plotOptions' => ['pie' => ['donut' => ['size' => '68%']]],
            ];
        }

        $count = count($this->series);

        return [
            'chart' => ['stacked' => $this->stacked, 'toolbar' => ['show' => false], 'zoom' => ['enabled' => false]],
            'dataLabels' => ['enabled' => false],
            'stroke' => [
                'width' => $this->type === self::BAR ? 0 : 2,
                'curve' => 'smooth',
                'dashArray' => array_map(fn (int $i): int => in_array($i, $this->dashed, true) ? 5 : 0, range(0, max(0, $count - 1))),
            ],
            'fill' => $this->type === self::AREA
                ? ['type' => 'gradient', 'gradient' => ['opacityFrom' => 0.35, 'opacityTo' => 0.02]]
                : ['opacity' => 1],
            'plotOptions' => ['bar' => ['columnWidth' => '60%', 'borderRadius' => 3]],
            'xaxis' => ['categories' => $this->labels, 'tickAmount' => min(10, max(1, count($this->labels) - 1))],
            'legend' => ['show' => $count > 1, 'position' => 'bottom'],
            'grid' => ['strokeDashArray' => 4],
        ];
    }

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.chart';
    }
}
