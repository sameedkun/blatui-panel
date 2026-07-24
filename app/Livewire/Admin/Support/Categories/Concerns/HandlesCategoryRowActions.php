<?php

namespace App\Livewire\Admin\Support\Categories\Concerns;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\Concerns\HasToast;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Plans\Concerns\HandlesPlanRowActions;
use App\Models\TicketCategory;

/**
 * Single-row category actions shared by the Categories index and (should one
 * ever be added) a category profile page — same pattern as
 * {@see HandlesPlanRowActions}.
 *
 * Requires the using component to also use {@see LogsAdminActivity}
 * and {@see HasToast}.
 */
trait HandlesCategoryRowActions
{
    /** The category awaiting delete confirmation, or null. */
    public ?int $deletingId = null;

    protected function hasTickets(TicketCategory $category): bool
    {
        return $category->tickets()->exists();
    }

    public function toggleActive(int $categoryId): void
    {
        $this->authorize('ticket_categories.edit');

        $category = TicketCategory::findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);

        $this->logActivity(ActivityModule::TicketCategory, ActivityAction::Updated, $category, [
            'attributes' => ['is_active' => $category->is_active],
        ]);

        $this->toastSuccess($category->name.($category->is_active ? ' activated.' : ' deactivated.'));
    }

    public function confirmDelete(int $categoryId): void
    {
        $this->authorize('ticket_categories.delete');

        $category = TicketCategory::findOrFail($categoryId);

        if ($this->hasTickets($category)) {
            $this->toastError("{$category->name} has tickets and cannot be deleted.", 'Deactivate it instead.');

            return;
        }

        $this->deletingId = $categoryId;
        $this->dispatch('open-alert-dialog-delete-category');
    }

    public function delete(): void
    {
        $this->authorize('ticket_categories.delete');

        $category = TicketCategory::findOrFail($this->deletingId);

        if ($this->hasTickets($category)) {
            $this->deletingId = null;
            $this->toastError("{$category->name} has tickets and cannot be deleted.", 'Deactivate it instead.');

            return;
        }

        $name = $category->name;
        $category->delete();

        $this->logActivity(ActivityModule::TicketCategory, ActivityAction::Deleted, null, [
            'attributes' => ['name' => $name],
        ]);

        $this->deletingId = null;
        $this->toastSuccess("{$name} deleted.");
    }
}
