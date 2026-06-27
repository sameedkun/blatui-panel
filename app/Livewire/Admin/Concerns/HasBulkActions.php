<?php

namespace App\Livewire\Admin\Concerns;

trait HasBulkActions
{
    /** IDs selected across all pages. */
    public array $selectedIds = [];

    /** The bulk action key currently awaiting confirmation, or null. */
    public ?string $confirmingBulkAction = null;

    /** Ban reason collected before bulk-ban confirmation. */
    public string $bulkBanReason = '';

    /**
     * Bulk action config. Each entry:
     *   key        => unique string identifier
     *   label      => display label
     *   icon       => lucide icon name (e.g. 'ban')
     *   confirm    => bool (requires alert-dialog confirmation)
     *   variant    => button variant (default: 'ghost')
     *   permission => gate/permission key
     *   when       => optional Closure($component): bool
     */
    protected function bulkActionConfig(): array
    {
        return [];
    }

    /** Returns actions filtered by `when` conditions. */
    public function getAvailableBulkActionsProperty(): array
    {
        return array_values(array_filter(
            $this->bulkActionConfig(),
            fn (array $action): bool => ! isset($action['when']) || ($action['when'])($this),
        ));
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->confirmingBulkAction = null;
        $this->bulkBanReason = '';
    }

    public function selectAllOnPage(array $pageIds): void
    {
        foreach ($pageIds as $id) {
            if (! in_array($id, $this->selectedIds, true)) {
                $this->selectedIds[] = $id;
            }
        }
    }

    public function deselectAllOnPage(array $pageIds): void
    {
        $this->selectedIds = array_values(array_diff($this->selectedIds, $pageIds));
    }

    public function allOnPageSelected(array $pageIds): bool
    {
        return ! empty($pageIds)
            && count(array_intersect($this->selectedIds, $pageIds)) === count($pageIds);
    }

    public function toggleSelection(string $id): void
    {
        $key = array_search($id, $this->selectedIds, true);

        if ($key !== false) {
            unset($this->selectedIds[$key]);
            $this->selectedIds = array_values($this->selectedIds);
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function openBulkConfirm(string $action): void
    {
        $this->confirmingBulkAction = $action;
        $this->bulkBanReason = '';

        // Most confirms use an alert-dialog; some (e.g. ban, which needs a reason
        // field) use a regular dialog. The action config may override the event.
        $config = collect($this->bulkActionConfig())->firstWhere('key', $action);
        $event = $config['dialog_event'] ?? "open-alert-dialog-bulk-{$action}";

        $this->dispatch($event);
    }

    public function cancelBulkAction(): void
    {
        $this->confirmingBulkAction = null;
        $this->bulkBanReason = '';
    }
}
