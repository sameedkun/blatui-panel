<?php

namespace App\Livewire\Admin\Concerns;

use App\Livewire\Admin\BaseShow;
use Livewire\Attributes\Url;

/**
 * Opt-in tab system for detail pages (typically used with {@see BaseShow}
 * and the <x-admin.show-tabs> component). Pages that want a tabbed profile use
 * this trait; plain record views leave it off.
 *
 * The page renders purely from the registry — there is no `if ($tab === '...')`
 * branch. Each registry entry names the Blade partial for its content, so adding
 * a tab is a one-line change to {@see tabs()}.
 */
trait HasShowTabs
{
    /** Active tab key. URL-bound so a tab is shareable and survives a refresh. */
    #[Url]
    public string $tab = '';

    /**
     * Ordered tab registry, keyed by tab key. Each entry:
     *   label       => string             Tab label
     *   view        => string             Blade partial rendered for the tab body
     *   icon?       => string             Lucide icon name (without the `lucide-` prefix)
     *   permission? => string             Gate ability required to see the tab
     *   data?       => callable(): array  Extra view data merged into the partial
     *
     * @return array<string, array<string, mixed>>
     */
    abstract protected function tabs(): array;

    /** Registry filtered to the tabs the current user is permitted to see. */
    protected function availableTabs(): array
    {
        return array_filter(
            $this->tabs(),
            fn (array $tab): bool => empty($tab['permission']) || auth()->user()->can($tab['permission']),
        );
    }

    /** The effective tab key: the URL value when valid, otherwise the first available tab. */
    public function resolveActiveTab(): string
    {
        $available = $this->availableTabs();

        return array_key_exists($this->tab, $available)
            ? $this->tab
            : (string) array_key_first($available);
    }

    /** Switch tabs (guarded to permitted tabs so a crafted call can't reach a hidden one). */
    public function selectTab(string $tab): void
    {
        if (array_key_exists($tab, $this->availableTabs())) {
            $this->tab = $tab;
        }
    }

    /** The active registry entry (or [] if none). */
    protected function activeTab(): array
    {
        return $this->availableTabs()[$this->resolveActiveTab()] ?? [];
    }

    /**
     * The Blade partial rendering the active tab's body. The shell renders this
     * without caring whether it points at a partial or (later) a lazy Livewire
     * component — swapping a renderer never touches the shell.
     */
    public function activeTabView(): ?string
    {
        return $this->activeTab()['view'] ?? null;
    }

    /**
     * Extra view data for the active tab, resolved from its optional `data`
     * callable. Lets a renderer receive computed data without the shell knowing
     * anything about it.
     *
     * @return array<string, mixed>
     */
    public function activeTabData(): array
    {
        $tab = $this->activeTab();

        return isset($tab['data']) && is_callable($tab['data']) ? ($tab['data'])() : [];
    }
}
