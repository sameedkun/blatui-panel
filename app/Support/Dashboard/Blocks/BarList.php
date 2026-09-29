<?php

namespace App\Support\Dashboard\Blocks;

use App\Support\Dashboard\Format;

/**
 * A ranked breakdown — label, value, and a proportional bar with its share.
 *
 * Used for "revenue by plan", "tickets by category", "status breakdown" and
 * anything else that reads best as a sorted list rather than a chart.
 *
 * @phpstan-consistent-constructor
 */
class BarList extends Block
{
    public ?string $description = null;

    public ?string $icon = null;

    /** @var list<array{label: string, value: int|float, hint: ?string, tone: ?string}> */
    public array $items = [];

    public bool $showShare = true;

    public function __construct(public string $title, public string $format = Format::NUMBER) {}

    public static function make(string $title, string $format = Format::NUMBER): static
    {
        return new static($title, $format);
    }

    /**
     * @param  string|null  $tone  `success`, `info`, `warning`, `danger` or null for the default bar colour.
     */
    public function item(string $label, int|float $value, ?string $hint = null, ?string $tone = null): static
    {
        $this->items[] = ['label' => $label, 'value' => $value, 'hint' => $hint, 'tone' => $tone];

        return $this;
    }

    /** @param  array<string, int|float>  $items  label => value */
    public function items(array $items): static
    {
        foreach ($items as $label => $value) {
            $this->item((string) $label, $value);
        }

        return $this;
    }

    public function sortDescending(): static
    {
        usort($this->items, fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return $this;
    }

    public function withoutShare(): static
    {
        $this->showShare = false;

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

    public function total(): float
    {
        return (float) array_sum(array_column($this->items, 'value'));
    }

    public function max(): float
    {
        return $this->items === [] ? 0.0 : (float) max(array_column($this->items, 'value'));
    }

    public function isEmpty(): bool
    {
        return $this->total() <= 0;
    }

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.bar-list';
    }
}
