<?php

namespace App\Support\Dashboard\Blocks;

use App\Support\Dashboard\Format;

/**
 * A domain status summary for the Overview — a few headline figures on top,
 * a proportional breakdown beneath ("Subscriptions: 842 active, 120 trialing
 * … split by status"). One card answers "is this area of the business okay?".
 *
 * @phpstan-consistent-constructor
 */
class StatusPanel extends Block
{
    public ?string $description = null;

    public ?string $icon = null;

    /** @var list<array{label: string, value: int|float|string|null, format: string}> */
    public array $figures = [];

    /** @var list<array{label: string, value: int|float, tone: ?string}> */
    public array $segments = [];

    public ?string $segmentsTitle = null;

    public function __construct(public string $title) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function figure(string $label, int|float|string|null $value, string $format = Format::NUMBER): static
    {
        $this->figures[] = ['label' => $label, 'value' => $value, 'format' => $format];

        return $this;
    }

    /**
     * @param  string|null  $tone  `success`, `info`, `warning`, `danger` or null.
     */
    public function segment(string $label, int|float $value, ?string $tone = null): static
    {
        $this->segments[] = ['label' => $label, 'value' => $value, 'tone' => $tone];

        return $this;
    }

    public function segmentsTitle(?string $title): static
    {
        $this->segmentsTitle = $title;

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

    public function segmentTotal(): float
    {
        return (float) array_sum(array_column($this->segments, 'value'));
    }

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.status-panel';
    }
}
