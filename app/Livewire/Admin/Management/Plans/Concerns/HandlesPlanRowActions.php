<?php

namespace App\Livewire\Admin\Management\Plans\Concerns;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\Concerns\HasToast;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Plans\Show;
use App\Models\Plan;

/**
 * Single-row plan actions (activate/deactivate, delete) shared by the Plans
 * index and the plan profile page — a toggle or delete from the profile header
 * runs the exact same code (and writes the exact same activity-log row) as
 * from the index.
 *
 * `delete()` here just deletes and stays on the page — right for the index,
 * where the row simply disappears from the list. The profile page has nowhere
 * to stay once its own record is gone, so {@see Show}
 * redefines `delete()` to redirect instead (same guard, same log call).
 *
 * Requires the using component to also use {@see LogsAdminActivity}
 * and {@see HasToast}. The matching confirm dialog is the shared
 * `plans/partials/dialogs.blade.php` (or profile equivalent) `delete-plan` alert-dialog.
 */
trait HandlesPlanRowActions
{
    /** The plan awaiting delete confirmation, or null. */
    public ?int $deletingId = null;

    protected function hasSubscriptions(Plan $plan): bool
    {
        return $plan->subscriptions()->exists();
    }

    public function toggleActive(int $planId): void
    {
        $this->authorize('plans.edit');

        $plan = Plan::findOrFail($planId);
        $plan->update(['is_active' => ! $plan->is_active]);

        $this->logActivity(ActivityModule::Plan, ActivityAction::Updated, $plan, [
            'attributes' => ['is_active' => $plan->is_active],
        ]);

        $this->toastSuccess($plan->is_active
            ? __('plans.toasts.activated', ['name' => $plan->name])
            : __('plans.toasts.deactivated', ['name' => $plan->name]));
    }

    public function confirmDelete(int $planId): void
    {
        $this->authorize('plans.delete');

        $plan = Plan::findOrFail($planId);

        if ($this->hasSubscriptions($plan)) {
            $this->toastError(
                __('plans.toasts.cannot_delete_with_subscriptions', ['name' => $plan->name]),
                __('plans.toasts.deactivate_to_retire'),
            );

            return;
        }

        $this->deletingId = $planId;
        $this->dispatch('open-alert-dialog-delete-plan');
    }

    public function delete(): void
    {
        $this->authorize('plans.delete');

        $plan = Plan::findOrFail($this->deletingId);

        if ($this->hasSubscriptions($plan)) {
            $this->deletingId = null;
            $this->toastError(
                __('plans.toasts.cannot_delete_with_subscriptions', ['name' => $plan->name]),
                __('plans.toasts.deactivate_to_retire'),
            );

            return;
        }

        $name = $plan->name;
        $plan->delete();

        $this->logActivity(ActivityModule::Plan, ActivityAction::Deleted, null, [
            'attributes' => ['name' => $name],
        ]);

        $this->deletingId = null;
        $this->toastSuccess(__('plans.toasts.deleted', ['name' => $name]));
    }
}
