<?php

namespace App\Livewire\Admin\Application\Announcement;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\AnnouncementPushStatus;
use App\Enum\AnnouncementType;
use App\Jobs\Announcement\SendPushNotification;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    use LogsAdminActivity;

    public ?int $announcementId = null;

    public string $title = '';

    public string $message = '';

    public string $type = 'general';

    public string $link = '';

    /** Create mode: send immediately, or leave unchecked to save as a draft for later. */
    public bool $sendNow = true;

    /** Edit mode: re-broadcast the push after saving changes. */
    public bool $resendAfterUpdate = false;

    public function mount(?Announcement $announcement = null): void
    {
        if ($announcement) {
            $this->isEditing = true;
            $this->announcementId = $announcement->id;
            $this->title = $announcement->title;
            $this->message = $announcement->message;
            $this->type = $announcement->type->value;
            $this->link = (string) $announcement->link;
        }
    }

    protected function indexRoute(): string
    {
        return 'admin.announcements.index';
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(AnnouncementType::class)],
            'link' => ['nullable', 'url', 'max:500'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'title' => __('announcements.validation_attributes.title'),
            'message' => __('announcements.validation_attributes.message'),
            'type' => __('announcements.validation_attributes.type'),
            'link' => __('announcements.validation_attributes.link'),
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => __('announcements.validation.title_required'),
            'title.string' => __('announcements.validation.title_invalid'),
            'title.max' => __('announcements.validation.title_max', ['max' => 255]),
            'message.required' => __('announcements.validation.message_required'),
            'message.string' => __('announcements.validation.message_invalid'),
            'message.max' => __('announcements.validation.message_max', ['max' => 2000]),
            'type.required' => __('announcements.validation.type_required'),
            'type.'.Enum::class => __('announcements.validation.type_invalid'),
            'link.url' => __('announcements.validation.link_url'),
            'link.max' => __('announcements.validation.link_max', ['max' => 500]),
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        $announcement = DB::transaction(function (): Announcement {
            if ($this->isEditing) {
                $announcement = Announcement::findOrFail($this->announcementId);
                $before = $announcement->getOriginal();

                $announcement->update([
                    'title' => $this->title,
                    'message' => $this->message,
                    'type' => $this->type,
                    'link' => $this->link ?: null,
                ]);

                $changes = $this->auditDiff($announcement, $before);
                if ($changes !== []) {
                    $this->logActivity(ActivityModule::Announcement, ActivityAction::Updated, $announcement, $changes);
                }
            } else {
                $announcement = Announcement::create([
                    'title' => $this->title,
                    'message' => $this->message,
                    'type' => $this->type,
                    'link' => $this->link ?: null,
                    'push_status' => $this->sendNow ? AnnouncementPushStatus::Pending : AnnouncementPushStatus::Draft,
                ]);

                $this->logActivity(ActivityModule::Announcement, ActivityAction::Created, $announcement, [
                    'attributes' => $announcement->only(['title', 'type', 'push_status']),
                ]);
            }

            return $announcement;
        });

        $willSend = $this->isEditing ? $this->resendAfterUpdate : $this->sendNow;

        if ($willSend) {
            $announcement->update(['push_status' => AnnouncementPushStatus::Pending, 'push_error' => null]);
            SendPushNotification::dispatch($announcement->id);
        }

        $message = match (true) {
            $this->isEditing && $willSend => __('announcements.toasts.updated_resend'),
            $this->isEditing => __('announcements.toasts.updated'),
            $willSend => __('announcements.toasts.created_sent'),
            default => __('announcements.toasts.created_draft'),
        };

        session()->flash('toast', ['type' => 'success', 'title' => $message]);

        // Sending lands back on the index with its status dialog already open
        // (?status={id}), so the live-updating push status is visible instantly.
        return $this->redirect(route(
            $this->indexRoute(),
            $willSend ? ['status' => $announcement->id] : [],
        ));
    }

    public function render(): View
    {
        return view('livewire.admin.application.announcement.form', [
            'typeOptions' => collect(AnnouncementType::cases())->mapWithKeys(fn (AnnouncementType $c) => [$c->value => $c->label()])->all(),
        ])->title($this->isEditing ? __('announcements.form.edit_title') : __('announcements.form.create_title'));
    }
}
