<?php

namespace App\Livewire\Admin\Concerns;

use App\Livewire\Admin\BaseShow;
use Livewire\Attributes\Url;
use Spatie\Activitylog\Models\Activity;

/**
 * In-place "view in detail" modal for one activity row on a record's Activity
 * tab — renders the same detail dialog as the Activity Logs viewer (shared via
 * `livewire.admin.system.activity-logs.partials.detail-dialog`), without
 * navigating away from the profile.
 *
 * Requires the using component to expose a `$record` property (i.e. a
 * {@see BaseShow} subclass): {@see selectedActivityDetail()}
 * scopes the lookup to that record, so an id for another subject silently
 * resolves to nothing rather than leaking another account's audit detail.
 */
trait HasActivityDetailModal
{
    #[Url]
    public ?int $selectedActivityId = null;

    public function viewActivityDetail(int $activityId): void
    {
        $this->selectedActivityId = $activityId;
        $this->dispatch('open-dialog-record-activity-detail');
    }

    public function clearSelectedActivityDetail(): void
    {
        $this->selectedActivityId = null;
    }

    protected function selectedActivityDetail(): ?Activity
    {
        if ($this->selectedActivityId === null) {
            return null;
        }

        return Activity::forSubject($this->record)
            ->with(['causer', 'subject'])
            ->find($this->selectedActivityId);
    }
}
