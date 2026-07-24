<?php

namespace App\Livewire\Admin\Application\Notification;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\NotificationPushStatus;
use App\Enum\NotificationType;
use App\Jobs\SendPushNotification;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Layout('layouts.admin.app')]
#[Title('Notifications')]
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
        return Notification::query();
    }

    protected function searchableColumns(): array
    {
        return ['title', 'message'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => $this->statusOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('push_status', $v),
            ],
            'type' => [
                'label' => 'Type',
                'type' => 'select',
                'options' => $this->typeOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('type', $v),
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => $this->statusOptions()],
            'type' => ['label' => 'Type', 'type' => 'select', 'options' => $this->typeOptions()],
        ];
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return collect(NotificationPushStatus::cases())->mapWithKeys(fn (NotificationPushStatus $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    private function typeOptions(): array
    {
        return collect(NotificationType::cases())->mapWithKeys(fn (NotificationType $c) => [$c->value => $c->label()])->all();
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => 'Total Notifications',
                'value' => fn () => Notification::count(),
                'icon' => 'bell',
                'description' => 'All-time broadcasts',
            ],
            [
                'label' => 'Sent',
                'value' => fn () => Notification::where('push_status', NotificationPushStatus::Sent)->count(),
                'icon' => 'check-circle',
                'description' => 'Delivered to OneSignal',
            ],
            [
                'label' => 'Failed',
                'value' => fn () => Notification::where('push_status', NotificationPushStatus::Failed)->count(),
                'icon' => 'circle-alert',
                'description' => 'Need a retry',
            ],
            [
                'label' => 'Drafts',
                'value' => fn () => Notification::where('push_status', NotificationPushStatus::Draft)->count(),
                'icon' => 'file-text',
                'description' => 'Not yet sent',
            ],
        ];
    }

    protected function bulkActionConfig(): array
    {
        return [
            [
                'key' => 'delete',
                'label' => 'Delete',
                'icon' => 'trash',
                'confirm' => true,
                'variant' => 'destructive',
                'permission' => 'notifications.delete',
            ],
        ];
    }

    public function confirmDelete(int $notificationId): void
    {
        $this->authorize('notifications.delete');

        $this->deletingId = $notificationId;
        $this->dispatch('open-alert-dialog-delete-notification');
    }

    public function delete(): void
    {
        $this->authorize('notifications.delete');

        $notification = Notification::findOrFail($this->deletingId);
        $title = $notification->title;
        $notification->delete();

        $this->logActivity(ActivityModule::Notification, ActivityAction::Deleted, null, [
            'attributes' => ['title' => $title],
        ]);

        $this->deletingId = null;
        $this->toastSuccess("{$title} deleted.");
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('notifications.delete');

        $ids = array_map('intval', $this->selectedIds);
        $count = Notification::query()->whereIn('id', $ids)->count();

        Notification::query()->whereIn('id', $ids)->delete();

        $this->logActivity(ActivityModule::Notification, ActivityAction::Deleted, null, [
            'bulk' => true,
            'notification_ids' => $ids,
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess("{$count} notifications deleted.");
    }

    /** Resend a previously sent notification, or retry a failed one — same flow either way. */
    public function resend(int $notificationId): void
    {
        $this->authorize('notifications.edit');

        $notification = Notification::findOrFail($notificationId);
        $notification->update(['push_status' => NotificationPushStatus::Pending, 'push_error' => null]);

        SendPushNotification::dispatch($notification->id);

        $this->toastSuccess("Push queued for \"{$notification->title}\".");
        $this->openStatusDialog($notificationId);
    }

    public function viewStatus(int $notificationId): void
    {
        $this->authorize('notifications.edit');

        $this->openStatusDialog($notificationId);
    }

    private function openStatusDialog(int $notificationId): void
    {
        $this->viewingStatusId = $notificationId;

        // The :open="..." binding on <x-ui.dialog> only takes effect on a fresh
        // mount (e.g. landing here via ?status={id}) — Alpine preserves its own
        // `open` state across a same-page Livewire morph, so a same-page click
        // needs this explicit dispatch to actually flip it.
        $this->dispatch('open-dialog-notification-status');
    }

    public function clearViewingStatus(): void
    {
        $this->viewingStatusId = null;
    }

    public function render(): View
    {
        $notifications = $this->getRecords();

        return view('livewire.admin.application.notification.index', [
            'notifications' => $notifications,
            'pageIds' => $notifications->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
            'viewingNotification' => $this->viewingStatusId ? Notification::find($this->viewingStatusId) : null,
        ]);
    }
}
