<?php

namespace App\Support\Dashboard\Blocks;

use App\Support\Dashboard\Format;

/**
 * A single KPI: a headline value, optionally compared with the previous period.
 *
 * @phpstan-consistent-constructor
 */
class Metric extends Block
{
    public int|float|null $previous = null;

    public ?string $description = null;

    public ?string $icon = null;

    /** A rise is bad news (open tickets, failed logins) — colour the trend by meaning, not direction. */
    public bool $invert = false;

    public function __construct(
        public string $label,
        public int|float|string|null $value,
        public string $format = Format::NUMBER,
    ) {}

    public static function make(string $label, int|float|string|null $value, string $format = Format::NUMBER): static
    {
        return new static($label, $value, $format);
    }

    public function compareTo(int|float|null $previous): static
    {
        $this->previous = $previous;

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

    public function invert(bool $invert = true): static
    {
        $this->invert = $invert;

        return $this;
    }

    public function formatted(): string
    {
        return Format::value($this->value, $this->format);
    }

    /** Period-over-period change in percent, or null when there is no comparable baseline. */
    public function change(): ?float
    {
        return is_numeric($this->value) ? Format::change((float) $this->value, $this->previous) : null;
    }

    /** `up`, `down`, `flat` or null — the trend's direction, independent of whether it is good. */
    public function direction(): ?string
    {
        $change = $this->change();

        return match (true) {
            $change === null => null,
            $change > 0 => 'up',
            $change < 0 => 'down',
            default => 'flat',
        };
    }

    /** `positive`, `negative` or `neutral` — how the trend should be coloured. */
    public function sentiment(): string
    {
        $direction = $this->direction();

        if ($direction === null || $direction === 'flat') {
            return 'neutral';
        }

        return ($direction === 'up') !== $this->invert ? 'positive' : 'negative';
    }

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.metric';
    }
}
