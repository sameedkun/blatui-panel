<?php

namespace App\Livewire\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasFilters
{
    public string $search = '';

    public array $filters = [];

    /**
     * Filter configuration. Each entry:
     *   type   => 'multi-select' | 'select' | 'date' | 'boolean'
     *   label  => string
     *   options => [value => label]  (for select/multi-select)
     *   apply  => Closure(Builder, mixed $value): Builder
     */
    protected function filterConfig(): array
    {
        return [];
    }

    /** Columns searched by $search (dot-notation for relations unsupported — keep to direct columns). */
    protected function searchableColumns(): array
    {
        return [];
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);
        $columns = $this->searchableColumns();

        if ($term === '' || empty($columns)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term, $columns): void {
            foreach ($columns as $col) {
                $q->orWhere($col, 'like', "%{$term}%");
            }
        });
    }

    protected function applyFilters(Builder $query): Builder
    {
        foreach ($this->filterConfig() as $key => $config) {
            $value = $this->filters[$key] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (isset($config['apply']) && is_callable($config['apply'])) {
                $query = ($config['apply'])($query, $value);
            }
        }

        return $query;
    }

    public function setFilter(string $key, mixed $value): void
    {
        $this->filters[$key] = $value;
        $this->resetPage();
    }

    public function toggleFilterValue(string $key, mixed $value): void
    {
        $current = array_values((array) ($this->filters[$key] ?? []));

        if (in_array($value, $current, true)) {
            $this->filters[$key] = array_values(array_filter($current, fn ($v) => $v !== $value));
        } else {
            $this->filters[$key] = [...$current, $value];
        }

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filters = [];
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        if ($this->search !== '') {
            return true;
        }

        foreach ($this->filters as $value) {
            if ($value !== '' && $value !== [] && $value !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    public function activeFilterKeys(): array
    {
        return array_keys(array_filter($this->filters, fn ($v) => $v !== '' && $v !== [] && $v !== null));
    }

    /** UI-facing filter config (no closures). Override per module. */
    protected function filterBarConfig(): array
    {
        return [];
    }

    /** Clear one or more specific filter keys. */
    public function clearFilters(string ...$keys): void
    {
        foreach ($keys as $key) {
            $this->filters[$key] = is_array($this->filters[$key] ?? null) ? [] : '';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
}
