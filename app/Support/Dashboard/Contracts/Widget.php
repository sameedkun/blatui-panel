<?php

namespace App\Support\Dashboard\Contracts;

use App\Support\Dashboard\Blocks\Block;
use App\Support\Dashboard\DateRange;

/**
 * One Overview widget: a permission plus a function from a date range to a
 * block. Widgets are listed per Overview slot in `config/dashboard.php`, so an
 * application adds, removes or reorders them there — never in the page.
 */
interface Widget
{
    /** Stable identity, used for its cache key. */
    public function key(): string;

    /** Gate ability required to see this widget, or null for any dashboard viewer. */
    public function permission(): ?string;

    /** The block to render, or null when the widget has nothing to say (it is then hidden). */
    public function build(DateRange $range): ?Block;
}
