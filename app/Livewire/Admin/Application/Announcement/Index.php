<?php

namespace App\Livewire\Admin\Application\Announcement;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\AnnouncementPushStatus;
use App\Enum\AnnouncementType;
use App\Jobs\Announcement\SendPushNotification;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    use LogsAdminActivity;

    public string $sortBy = 'created_at';

    public string $sortDir = 'desc';

    public array $filters = [
        'status' => '',
        'type' => '',
    ];

    public ?int $deletingId = null;

    /** Bound to the URL so the Form page can redirect straight into an open status dialog. */
    #[Url(as: 'status')]
    public ?int $viewingStatusId = null;

    protected function baseQuery(): Builder
    {
        return Announcement::query();
    }

    protected function searchableColumns(): array
    {
        return ['title', 'message'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => __('announcements.fields.status'),
                'type' => 'select',
                'options' => $this->statusOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('push_status', $v),
            ],
            'type' => [
                'label' => __('announcements.fields.type'),
                'type' => 'select',
                'options' => $this->typeOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('type', $v),
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'status' => ['label' => __('announcements.fields.status'), 'type' => 'select', 'options' => $this->statusOptions()],
            'type' => ['label' => __('announcements.fields.type'), 'type' => 'select', 'options' => $this->typeOptions()],
        ];
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return collect(AnnouncementPushStatus::cases())->mapWithKeys(fn (AnnouncementPushStatus $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    private function typeOptions(): array
    {
        return collect(AnnouncementType::cases())->mapWithKeys(fn (AnnouncementType $c) => [$c->value => $c->label()])->all();
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => __('announcements.stats.total'),
                'value' => fn () => Announcement::count(),
                'icon' => 'bell',
                'description' => __('announcements.stats.total_description'),
            ],
            [
                'label' => __('announcements.stats.sent'),
                'value' => fn () => Announcement::where('push_status', AnnouncementPushStatus::Sent)->count(),
                'icon' => 'check-circle',
                'description' => __('announcements.stats.sent_description'),
            ],
            [
                'label' => __('announcements.stats.failed'),
                'value' => fn () => Announcement::where('push_status', AnnouncementPushStatus::Failed)->count(),
                'icon' => 'circle-alert',
                'description' => __('announcements.stats.failed_description'),
            ],
            [
                'label' => __('announcements.stats.drafts'),
                'value' => fn () => Announcement::where('push_status', AnnouncementPushStatus::Draft)->count(),
                'icon' => 'file-text',
                'description' => __('announcements.stats.drafts_description'),
            ],
        ];
    }

    protected function bulkActionConfig(): array
    {
        return [
            [
                'key' => 'delete',
                'label' => __('announcements.actions.delete'),
                'icon' => 'trash',
                'confirm' => true,
                'variant' => 'destructive',
                'permission' => 'announcements.delete',
            ],
        ];
    }

    public function confirmDelete(int $announcementId): void
    {
        $this->authorize('announcements.delete');

        $this->deletingId = $announcementId;
        $this->dispatch('open-alert-dialog-delete-announcement');
    }

    public function delete(): void
    {
        $this->authorize('announcements.delete');

        $announcement = Announcement::findOrFail($this->deletingId);
        $title = $announcement->title;
        $announcement->delete();

        $this->logActivity(ActivityModule::Announcement, ActivityAction::Deleted, null, [
            'attributes' => ['title' => $title],
        ]);

        $this->deletingId = null;
        $this->toastSuccess(__('announcements.toasts.deleted', ['title' => $title]));
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('announcements.delete');

        $ids = array_map('intval', $this->selectedIds);
        $count = Announcement::query()->whereIn('id', $ids)->count();

        Announcement::query()->whereIn('id', $ids)->delete();

        $this->logActivity(ActivityModule::Announcement, ActivityAction::Deleted, null, [
            'bulk' => true,
            'announcement_ids' => $ids,
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess(__('announcements.toasts.bulk_deleted', ['count' => $count]));
    }

    /** Resend a previously sent announcement, or retry a failed one — same flow either way. */
    public function resend(int $announcementId): void
    {
        $this->authorize('announcements.edit');

        $announcement = Announcement::findOrFail($announcementId);
        $announcement->update(['push_status' => AnnouncementPushStatus::Pending, 'push_error' => null]);

        SendPushNotification::dispatch($announcement->id);

        $this->toastSuccess(__('announcements.toasts.push_queued', ['title' => $announcement->title]));
        $this->openStatusDialog($announcementId);
    }

    public function viewStatus(int $announcementId): void
    {
        $this->authorize('announcements.edit');

        $this->openStatusDialog($announcementId);
    }

    private function openStatusDialog(int $announcementId): void
    {
        $this->viewingStatusId = $announcementId;

        // The :open="..." binding on <x-ui.dialog> only takes effect on a fresh
        // mount (e.g. landing here via ?status={id}) — Alpine preserves its own
        // `open` state across a same-page Livewire morph, so a same-page click
        // needs this explicit dispatch to actually flip it.
        $this->dispatch('open-dialog-announcement-status');
    }

    public function clearViewingStatus(): void
    {
        $this->viewingStatusId = null;
    }

    public function render(): View
    {
        $announcements = $this->getRecords();

        return view('livewire.admin.application.announcement.index', [
            'announcements' => $announcements,
            'pageIds' => $announcements->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
            'viewingAnnouncement' => $this->viewingStatusId ? Announcement::find($this->viewingStatusId) : null,
        ])->title(__('announcements.title'));
    }
}
