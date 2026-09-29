<?php

namespace App\Support\Dashboard\Blocks;

/**
 * One renderable dashboard unit — a KPI, a chart, a breakdown, a table.
 *
 * Blocks are plain value objects: widgets and analytics sections build them
 * from metric queries, and a single generic Blade renderer draws any of them
 * by including {@see view()}. That split is what lets an application add its
 * own analytics (VPN nodes, inference usage, …) without writing any dashboard
 * Blade at all — or ship a bespoke Block subclass with its own view when it
 * genuinely needs one. Being plain data, a built block is safe to cache.
 */
abstract class Block
{
    /** Grid columns this block spans inside its {@see Row} (1–4). */
    public int $span = 1;

    /** Optional link shown in the block header ("View all"). */
    public ?string $href = null;

    /** Gate ability required to show {@see $href}; checked at render time, never cached per user. */
    public ?string $hrefPermission = null;

    /** The Blade partial that renders this block. */
    abstract public function view(): string;

    public function span(int $columns): static
    {
        $this->span = max(1, min(4, $columns));

        return $this;
    }

    public function link(string $href, ?string $permission = null): static
    {
        $this->href = $href;
        $this->hrefPermission = $permission;

        return $this;
    }

    /** Stable identity for wire:key — changes whenever the block's data changes. */
    public function fingerprint(): string
    {
        return md5(static::class.serialize(get_object_vars($this)));
    }
}
