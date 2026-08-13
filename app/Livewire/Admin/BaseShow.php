<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Livewire\Admin\Concerns\HasToast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Abstract base for admin detail/profile pages — a single bound record shown on
 * its own page. Holds only what every detail page shares: the bound record, view
 * authorization, and a title/breadcrumb derived from the record.
 *
 * Like {@see BaseForm} and {@see BaseIndex}, the base picks no view and imposes
 * no layout — each concrete page writes its own render() and declares its own
 * #[Layout]. A page that wants a tabbed profile opts in with the
 * {@see HasShowTabs} trait plus the
 * <x-admin.show-tabs> component; a plain record view uses neither.
 */
abstract class BaseShow extends Component
{
    use HasToast;

    /** The record being shown (route-model bound in the concrete mount()). */
    public Model $record;

    /**
     * Bind the record and enforce view authorization. Concrete pages call this
     * from their typed mount() so route-model binding stays on the child.
     */
    protected function initShow(Model $record): void
    {
        if ($permission = $this->viewPermission()) {
            $this->authorize($permission);
        }

        $this->record = $record;
    }

    /** Named index route the breadcrumb links back to. */
    abstract protected function indexRoute(): string;

    /** Page title derived from the record. */
    abstract protected function title(): string;

    /** Gate ability required to view the page; null defers to route middleware. */
    protected function viewPermission(): ?string
    {
        return null;
    }

    /**
     * Home → {index} → {record title}, derived from the index route and title().
     *
     * @return array<int, array<string, string>>
     */
    protected function breadcrumbs(): array
    {
        $parts = explode('.', $this->indexRoute());
        $resource = $parts[count($parts) - 2] ?? '';
        $resourceTitle = __("{$resource}.title");

        return [
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => $resourceTitle !== "{$resource}.title" ? $resourceTitle : Str::headline($resource), 'url' => route($this->indexRoute())],
            ['label' => $this->title()],
        ];
    }
}
