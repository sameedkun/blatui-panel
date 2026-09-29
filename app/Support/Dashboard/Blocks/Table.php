<?php

namespace App\Support\Dashboard\Blocks;

use App\Support\Dashboard\Format;

/**
 * A compact data table (agent performance, busiest endpoints, …).
 *
 * Cells hold raw values; each column declares its own format and alignment,
 * so the renderer never needs to know what a column means.
 *
 * @phpstan-consistent-constructor
 */
class Table extends Block
{
    public ?string $description = null;

    public ?string $icon = null;

    /** @var array<string, array{label: string, format: string, align: string}> */
    public array $columns = [];

    /** @var list<array<string, int|float|string|null>> */
    public array $rows = [];

    public function __construct(public string $title) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function column(string $key, string $label, string $format = Format::TEXT, ?string $align = null): static
    {
        $this->columns[$key] = [
            'label' => $label,
            'format' => $format,
            'align' => $align ?? ($format === Format::TEXT ? 'left' : 'right'),
        ];

        return $this;
    }

    /** @param  iterable<array<string, int|float|string|null>>  $rows */
    public function rows(iterable $rows): static
    {
        foreach ($rows as $row) {
            $this->rows[] = $row;
        }

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

    public function cell(array $row, string $key): string
    {
        return Format::value($row[$key] ?? null, $this->columns[$key]['format'] ?? Format::TEXT);
    }

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.table';
    }
}
