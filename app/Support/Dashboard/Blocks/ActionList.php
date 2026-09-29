<?php

namespace App\Support\Dashboard\Blocks;

/**
 * Quick-action shortcuts. Each action carries the permission that gates it,
 * checked at render time so one cached list serves every viewer.
 *
 * @phpstan-consistent-constructor
 */
class ActionList extends Block
{
    public ?string $description = null;

    public ?string $icon = null;

    /** @var list<array{label: string, description: ?string, icon: string, href: string, permission: ?string}> */
    public array $actions = [];

    public function __construct(public string $title) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function action(string $label, string $href, string $icon = 'arrow-right', ?string $description = null, ?string $permission = null): static
    {
        $this->actions[] = compact('label', 'description', 'icon', 'href', 'permission');

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
        return 'livewire.admin.dashboard.blocks.action-list';
    }
}
