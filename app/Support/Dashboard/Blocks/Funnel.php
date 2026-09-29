<?php

namespace App\Support\Dashboard\Blocks;

/**
 * An ordered sequence of stages — a conversion funnel, or a lifecycle flow
 * (New → Active → Renewed → Expiring → Cancelled).
 *
 * With {@see $conversion} on, each step also shows its share of the previous
 * one; off, the steps are independent counts that only share an order.
 *
 * @phpstan-consistent-constructor
 */
class Funnel extends Block
{
    public ?string $description = null;

    public ?string $icon = null;

    /** @var list<array{label: string, value: int|float, hint: ?string, tone: ?string}> */
    public array $steps = [];

    public bool $conversion = true;

    public function __construct(public string $title) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function step(string $label, int|float $value, ?string $hint = null, ?string $tone = null): static
    {
        $this->steps[] = ['label' => $label, 'value' => $value, 'hint' => $hint, 'tone' => $tone];

        return $this;
    }

    public function withoutConversion(): static
    {
        $this->conversion = false;

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

    /** Share of the previous step, or null for the first step / an empty predecessor. */
    public function conversionAt(int $index): ?float
    {
        if (! $this->conversion || $index === 0) {
            return null;
        }

        $previous = (float) $this->steps[$index - 1]['value'];

        return $previous > 0 ? round(($this->steps[$index]['value'] / $previous) * 100, 1) : null;
    }

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.funnel';
    }
}
