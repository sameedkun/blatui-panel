<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasBulkActions;
use App\Livewire\Admin\Concerns\HasFilters;
use App\Livewire\Admin\Concerns\HasStats;
use App\Livewire\Admin\Concerns\HasToast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

abstract class BaseIndex extends Component
{
    use HasBulkActions;
    use HasFilters;
    use HasStats;
    use HasToast;
    use WithPagination;

    public int $perPage = 10;

    public string $sortBy = 'id';

    public string $sortDir = 'desc';

    /** Base Eloquent query — subclasses handle soft-delete scoping, eager loads, etc. */
    abstract protected function baseQuery(): Builder;

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    protected function getRecords(): LengthAwarePaginator
    {
        return $this->applySearch($this->applyFilters($this->baseQuery()))
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);
    }

    public function toggleSelectPage(array $pageIds): void
    {
        if ($this->allOnPageSelected($pageIds)) {
            $this->deselectAllOnPage($pageIds);
        } else {
            $this->selectAllOnPage($pageIds);
        }
    }
}
