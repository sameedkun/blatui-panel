<?php

namespace App\Livewire\Admin\Application\Feedback;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\FeedbackStatus;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use LogsAdminActivity;

    public string $adminNotes = '';

    public function mount(Feedback $feedback): void
    {
        $this->initShow($feedback);
        $this->adminNotes = (string) $feedback->admin_notes;
    }

    protected function indexRoute(): string
    {
        return 'admin.feedback.index';
    }

    protected function title(): string
    {
        return $this->record->subject ?: 'Feedback #'.$this->record->id;
    }

    protected function viewPermission(): ?string
    {
        return 'feedback.manage';
    }

    /** The account matching this submission's email, if any (never itself the submitting user). */
    public function matchingAccount(): ?User
    {
        /** @var Feedback $feedback */
        $feedback = $this->record;

        if ($feedback->user_id) {
            return null;
        }

        return $feedback->matchingAccount();
    }

    public function markAsRead(): void
    {
        $this->authorize('feedback.manage');

        /** @var Feedback $feedback */
        $feedback = $this->record;

        if ($feedback->status !== FeedbackStatus::New) {
            return;
        }

        $feedback->update(['status' => FeedbackStatus::Read, 'read_at' => now()]);

        $this->logActivity(ActivityModule::Feedback, ActivityAction::Updated, $feedback, [
            'type' => 'feedback_status_changed',
            'status' => FeedbackStatus::Read->value,
        ]);

        $this->toastSuccess('Marked as read.');
    }

    public function resolve(): void
    {
        $this->authorize('feedback.manage');

        /** @var Feedback $feedback */
        $feedback = $this->record;

        $feedback->update([
            'status' => FeedbackStatus::Resolved,
            'read_at' => $feedback->read_at ?? now(),
            'resolved_at' => now(),
        ]);

        $this->logActivity(ActivityModule::Feedback, ActivityAction::Updated, $feedback, [
            'type' => 'feedback_status_changed',
            'status' => FeedbackStatus::Resolved->value,
        ]);

        $this->toastSuccess('Feedback resolved.');
    }

    public function ignore(): void
    {
        $this->authorize('feedback.manage');

        /** @var Feedback $feedback */
        $feedback = $this->record;

        $feedback->update([
            'status' => FeedbackStatus::Ignored,
            'read_at' => $feedback->read_at ?? now(),
        ]);

        $this->logActivity(ActivityModule::Feedback, ActivityAction::Updated, $feedback, [
            'type' => 'feedback_status_changed',
            'status' => FeedbackStatus::Ignored->value,
        ]);

        $this->toastSuccess('Feedback ignored.');
    }

    public function reopen(): void
    {
        $this->authorize('feedback.manage');

        /** @var Feedback $feedback */
        $feedback = $this->record;

        $feedback->update(['status' => FeedbackStatus::Read, 'resolved_at' => null]);

        $this->logActivity(ActivityModule::Feedback, ActivityAction::Updated, $feedback, [
            'type' => 'feedback_status_changed',
            'status' => FeedbackStatus::Read->value,
        ]);

        $this->toastSuccess('Feedback reopened.');
    }

    public function saveNotes(): void
    {
        $this->authorize('feedback.manage');

        $this->validate(['adminNotes' => ['nullable', 'string', 'max:5000']]);

        /** @var Feedback $feedback */
        $feedback = $this->record;

        $feedback->update(['admin_notes' => $this->adminNotes ?: null]);

        $this->logActivity(ActivityModule::Feedback, ActivityAction::Updated, $feedback, [
            'type' => 'feedback_notes_updated',
        ]);

        $this->toastSuccess('Notes saved.');
    }

    public function render(): View
    {
        if ($fresh = $this->record->fresh('user')) {
            $this->record = $fresh;
        }

        return view('livewire.admin.application.feedback.show', [
            'matchingAccount' => $this->matchingAccount(),
        ]);
    }
}
