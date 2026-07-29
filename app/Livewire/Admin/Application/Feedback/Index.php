<?php

namespace App\Livewire\Admin\Application\Feedback;

use App\Enum\FeedbackStatus;
use App\Enum\FeedbackType;
use App\Livewire\Admin\BaseIndex;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
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
                'label' => __('feedback.fields.status'),
                'type' => 'select',
                'options' => $this->statusOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('status', $v),
            ],
            'type' => [
                'label' => __('feedback.fields.type'),
                'type' => 'select',
                'options' => $this->typeOptions(),
                'apply' => fn (Builder $q, string $v): Builder => $q->where('type', $v),
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'status' => ['label' => __('feedback.fields.status'), 'type' => 'select', 'options' => $this->statusOptions()],
            'type' => ['label' => __('feedback.fields.type'), 'type' => 'select', 'options' => $this->typeOptions()],
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
                'label' => __('feedback.stats.total'),
                'value' => fn () => Feedback::count(),
                'icon' => 'message-square-quote',
                'description' => __('feedback.stats.total_description'),
            ],
            [
                'label' => __('feedback.stats.new'),
                'value' => fn () => Feedback::where('status', FeedbackStatus::New)->count(),
                'icon' => 'circle-dot',
                'description' => __('feedback.stats.new_description'),
            ],
            [
                'label' => __('feedback.stats.resolved'),
                'value' => fn () => Feedback::where('status', FeedbackStatus::Resolved)->count(),
                'icon' => 'check-circle',
                'description' => __('feedback.stats.resolved_description'),
            ],
            [
                'label' => __('feedback.stats.anonymous'),
                'value' => fn () => Feedback::whereNull('user_id')->count(),
                'icon' => 'user-x',
                'description' => __('feedback.stats.anonymous_description'),
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
        ])->title(__('feedback.title'));
    }
}
