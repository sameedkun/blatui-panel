<?php

namespace App\Support\Dashboard\Blocks;

/**
 * A horizontal band of blocks in an analytics section.
 *
 * {@see $columns} is the grid width at the widest breakpoint (1–4); narrower
 * screens collapse it responsively in the renderer. Blocks widen themselves
 * with {@see Block::span()}.
 */
final class Row
{
    /** @var list<Block> */
    public array $blocks;

    public function __construct(public int $columns, Block ...$blocks)
    {
        $this->columns = max(1, min(4, $columns));
        $this->blocks = array_values($blocks);
    }

    /** A row whose width is the combined span of its blocks. */
    public static function of(Block ...$blocks): self
    {
        $span = array_sum(array_map(fn (Block $block): int => $block->span, $blocks));

        return new self(max(1, $span), ...$blocks);
    }

    public static function columns(int $columns, Block ...$blocks): self
    {
        return new self($columns, ...$blocks);
    }

    /** Whether every block is a KPI — rendered as a compact metric strip. */
    public function isMetricStrip(): bool
    {
        foreach ($this->blocks as $block) {
            if (! $block instanceof Metric) {
                return false;
            }
        }

        return $this->blocks !== [];
    }
}
