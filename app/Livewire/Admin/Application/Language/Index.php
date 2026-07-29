<?php

namespace App\Livewire\Admin\Application\Language;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseIndex;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Language;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    use LogsAdminActivity;

    public string $sortBy = 'sort_order';

    public string $sortDir = 'asc';

    public ?int $deletingId = null;

    protected function baseQuery(): Builder
    {
        return Language::query();
    }

    protected function searchableColumns(): array
    {
        return ['name', 'native_name', 'code'];
    }

    protected function filterConfig(): array
    {
        return [
            'status' => [
                'label' => __('languages.fields.status'),
                'type' => 'multi-select',
                'options' => [
                    'active' => __('languages.status.active'),
                    'inactive' => __('languages.status.inactive'),
                ],
                'apply' => function (Builder $q, array $values): Builder {
                    return $q->where(function (Builder $sub) use ($values): void {
                        foreach ($values as $v) {
                            match ($v) {
                                'active' => $sub->orWhere('is_active', true),
                                'inactive' => $sub->orWhere('is_active', false),
                                default => null,
                            };
                        }
                    });
                },
            ],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'status' => [
                'label' => __('languages.fields.status'),
                'type' => 'multi-select',
                'options' => [
                    'active' => __('languages.status.active'),
                    'inactive' => __('languages.status.inactive'),
                ],
            ],
        ];
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => __('languages.stats.total'),
                'value' => fn () => Language::count(),
                'icon' => 'globe',
                'description' => __('languages.stats.total_description'),
            ],
            [
                'label' => __('languages.stats.active'),
                'value' => fn () => Language::where('is_active', true)->count(),
                'icon' => 'check-circle',
                'description' => __('languages.stats.active_description'),
            ],
            [
                'label' => __('languages.stats.inactive'),
                'value' => fn () => Language::where('is_active', false)->count(),
                'icon' => 'circle-off',
                'description' => __('languages.stats.inactive_description'),
            ],
            [
                'label' => __('languages.stats.rtl'),
                'value' => fn () => Language::where('is_rtl', true)->count(),
                'icon' => 'flip-horizontal',
                'description' => __('languages.stats.rtl_description'),
            ],
        ];
    }

    protected function bulkActionConfig(): array
    {
        return [
            [
                'key' => 'delete',
                'label' => __('languages.actions.delete'),
                'icon' => 'trash',
                'confirm' => true,
                'variant' => 'destructive',
                'permission' => 'languages.delete',
            ],
        ];
    }

    public function confirmDelete(int $languageId): void
    {
        $this->authorize('languages.delete');

        $language = Language::findOrFail($languageId);
        abort_if($language->is_default, 403, __('languages.validation.default_cannot_delete'));

        $this->deletingId = $languageId;
        $this->dispatch('open-alert-dialog-delete-language');
    }

    public function delete(): void
    {
        $this->authorize('languages.delete');

        $language = Language::findOrFail($this->deletingId);
        abort_if($language->is_default, 403, __('languages.validation.default_cannot_delete'));

        $name = $language->name;
        $language->delete();

        $this->logActivity(ActivityModule::Language, ActivityAction::Deleted, null, [
            'attributes' => ['name' => $name, 'code' => $language->code],
        ]);

        $this->deletingId = null;
        $this->toastSuccess(__('languages.toasts.deleted', ['name' => $name]));
    }

    public function executeBulkDelete(): void
    {
        $this->authorize('languages.delete');

        $ids = array_map('intval', $this->selectedIds);
        $languages = Language::query()->whereIn('id', $ids)->where('is_default', false)->get();
        $count = $languages->count();

        Language::query()->whereIn('id', $languages->pluck('id'))->delete();

        $this->logActivity(ActivityModule::Language, ActivityAction::Deleted, null, [
            'bulk' => true,
            'language_ids' => $languages->pluck('id')->all(),
            'count' => $count,
        ]);

        $this->clearSelection();
        $this->toastSuccess(__('languages.toasts.bulk_deleted', ['count' => $count]));
    }

    public function render(): View
    {
        $languages = $this->getRecords();

        return view('livewire.admin.application.language.index', [
            'languages' => $languages,
            'pageIds' => $languages->reject(fn (Language $language): bool => $language->is_default)
                ->pluck('id')->map(fn ($id) => (string) $id)->toArray(),
            'stats' => $this->resolveStats(),
            'filterBarConfig' => $this->filterBarConfig(),
        ])->title(__('languages.title'));
    }
}
