<?php

namespace App\Support\Dashboard\Overview;

use App\Support\Dashboard\Contracts\Widget;
use Illuminate\Support\Str;

/**
 * Convenience base for Overview widgets — derives the key from the class name
 * and reads the permission from a property, so a widget is just its build().
 */
abstract class OverviewWidget implements Widget
{
    protected ?string $permission = null;

    public function key(): string
    {
        return Str::snake(class_basename(static::class));
    }

    public function permission(): ?string
    {
        return $this->permission;
    }
}
