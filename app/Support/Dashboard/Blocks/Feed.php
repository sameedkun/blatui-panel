<?php

namespace App\Support\Dashboard\Blocks;

/**
 * A chronological list of events — recent activity, security events.
 *
 * Times are stored as ISO-8601 strings (rendered client-side in the viewer's
 * timezone), never as Carbon objects, so a cached feed stays plain data.
 *
 * @phpstan-consistent-constructor
 */
class Feed extends Block
{
    public ?string $description = null;

    public ?string $icon = null;

    /** @var list<array{title: string, description: ?string, time: ?string, icon: string, tone: string, href: ?string}> */
    public array $items = [];

    public ?string $emptyMessage = null;

    public function __construct(public string $title) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    /**
     * @param  string  $tone  `success`, `info`, `warning`, `danger` or `muted`.
     */
    public function item(string $title, ?string $description = null, ?string $time = null, string $icon = 'activity', string $tone = 'muted', ?string $href = null): static
    {
        $this->items[] = compact('title', 'description', 'time', 'icon', 'tone', 'href');

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

    public function emptyMessage(string $message): static
    {
        $this->emptyMessage = $message;

        return $this;
    }

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.feed';
    }
}
