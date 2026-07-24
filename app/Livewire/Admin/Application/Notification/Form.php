<?php

namespace App\Livewire\Admin\Application\Notification;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\NotificationPushStatus;
use App\Enum\NotificationType;
use App\Jobs\SendPushNotification;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    use LogsAdminActivity;

    public ?int $notificationId = null;

    public string $title = '';

    public string $message = '';

    public string $type = 'general';

    public string $link = '';

    /** Create mode: send immediately, or leave unchecked to save as a draft for later. */
    public bool $sendNow = true;

    /** Edit mode: re-broadcast the push after saving changes. */
    public bool $resendAfterUpdate = false;

    public function mount(?Notification $notification = null): void
    {
        if ($notification) {
            $this->isEditing = true;
            $this->notificationId = $notification->id;
            $this->title = $notification->title;
            $this->message = $notification->message;
            $this->type = $notification->type->value;
            $this->link = (string) $notification->link;
        }
    }

    protected function indexRoute(): string
    {
        return 'admin.notifications.index';
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(NotificationType::class)],
            'link' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        $notification = DB::transaction(function (): Notification {
            if ($this->isEditing) {
                $notification = Notification::findOrFail($this->notificationId);
                $before = $notification->getOriginal();

                $notification->update([
                    'title' => $this->title,
                    'message' => $this->message,
                    'type' => $this->type,
                    'link' => $this->link ?: null,
                ]);

                $changes = $this->auditDiff($notification, $before);
                if ($changes !== []) {
                    $this->logActivity(ActivityModule::Notification, ActivityAction::Updated, $notification, $changes);
                }
            } else {
                $notification = Notification::create([
                    'title' => $this->title,
                    'message' => $this->message,
                    'type' => $this->type,
                    'link' => $this->link ?: null,
                    'push_status' => $this->sendNow ? NotificationPushStatus::Pending : NotificationPushStatus::Draft,
                ]);

                $this->logActivity(ActivityModule::Notification, ActivityAction::Created, $notification, [
                    'attributes' => $notification->only(['title', 'type', 'push_status']),
                ]);
            }

            return $notification;
        });

        $willSend = $this->isEditing ? $this->resendAfterUpdate : $this->sendNow;

        if ($willSend) {
            $notification->update(['push_status' => NotificationPushStatus::Pending, 'push_error' => null]);
            SendPushNotification::dispatch($notification->id);
        }

        $message = $this->isEditing
            ? 'Notification updated'.($willSend ? ' — push resend queued.' : '.')
            : 'Notification created'.($willSend ? ' — push queued.' : ' as a draft.');

        session()->flash('toast', ['type' => 'success', 'title' => $message]);

        // Sending lands back on the index with its status dialog already open
        // (?status={id}), so the live-updating push status is visible instantly.
        return $this->redirect(route(
            $this->indexRoute(),
            $willSend ? ['status' => $notification->id] : [],
        ));
    }

    public function render(): View
    {
        return view('livewire.admin.application.notification.form', [
            'typeOptions' => collect(NotificationType::cases())->mapWithKeys(fn (NotificationType $c) => [$c->value => $c->label()])->all(),
        ]);
    }
}
