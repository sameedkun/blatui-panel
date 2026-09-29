<?php

namespace App\Support\Dashboard\Blocks;

use App\Support\Dashboard\Format;

/**
 * A card of secondary figures side by side — "Registration rate | Activation
 * | Retention | Conversion". Lighter-weight than a row of {@see Metric}s, for
 * numbers that support the page rather than headline it.
 *
 * @phpstan-consistent-constructor
 */
class KeyFigures extends Block
{
    public ?string $description = null;

    public ?string $icon = null;

    /** @var list<array{label: string, value: int|float|string|null, format: string, hint: ?string}> */
    public array $figures = [];

    public function __construct(public string $title) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function figure(string $label, int|float|string|null $value, string $format = Format::NUMBER, ?string $hint = null): static
    {
        $this->figures[] = ['label' => $label, 'value' => $value, 'format' => $format, 'hint' => $hint];

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

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.key-figures';
    }
}
