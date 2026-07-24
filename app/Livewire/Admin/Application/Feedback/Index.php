<?php

namespace App\Livewire\Admin\Application\Feedback;

use App\Enum\FeedbackStatus;
use App\Enum\FeedbackType;
use App\Livewire\Admin\BaseIndex;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.admin.app')]
#[Title('Feedback')]
class Index extends BaseIndex
{
    public string $sortBy = 'created_at';

    public string $sortDir = 'desc';

    public array $filters = [
        'status' => '',
        'type' => '',
    ];

    protected function baseQuery(): Builder
    {
        return Feedback::query()->with('user');
    }

    protected function searchableColumns(): array
    {
        return ['subject', 'message', 'email'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => $this->statusOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('status', $v),
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
        return collect(FeedbackStatus::cases())->mapWithKeys(fn (FeedbackStatus $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<string, string> */
    private function typeOptions(): array
    {
        return collect(FeedbackType::cases())->mapWithKeys(fn (FeedbackType $c) => [$c->value => $c->label()])->all();
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => 'Total Feedback',
                'value' => fn () => Feedback::count(),
                'icon' => 'message-square-quote',
                'description' => 'All-time submissions',
            ],
            [
                'label' => 'New',
                'value' => fn () => Feedback::where('status', FeedbackStatus::New)->count(),
                'icon' => 'circle-dot',
                'description' => 'Awaiting review',
            ],
            [
                'label' => 'Resolved',
                'value' => fn () => Feedback::where('status', FeedbackStatus::Resolved)->count(),
                'icon' => 'check-circle',
                'description' => 'Handled submissions',
            ],
            [
                'label' => 'Anonymous',
                'value' => fn () => Feedback::whereNull('user_id')->count(),
                'icon' => 'user-x',
                'description' => 'Submitted without an account',
            ],
        ];
    }

    public function render(): View
    {
        $feedback = $this->getRecords();

        return view('livewire.admin.application.feedback.index', [
            'feedback' => $feedback,
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ]);
    }
}
