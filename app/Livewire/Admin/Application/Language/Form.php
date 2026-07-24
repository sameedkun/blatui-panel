<?php

namespace App\Livewire\Admin\Application\Language;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Language;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    use LogsAdminActivity;

    public ?int $languageId = null;

    public string $name = '';

    public string $native_name = '';

    public string $code = '';

    public string $flag = '';

    public bool $is_rtl = false;

    public bool $is_default = false;

    public bool $is_active = true;

    public int $sort_order = 0;

    public string $translations = '';

    protected function indexRoute(): string
    {
        return 'admin.languages.index';
    }

    public function mount(?Language $language = null): void
    {
        if ($language) {
            $this->isEditing = true;
            $this->languageId = $language->id;
            $this->name = $language->name;
            $this->native_name = (string) $language->native_name;
            $this->code = $language->code;
            $this->flag = (string) $language->flag;
            $this->is_rtl = $language->is_rtl;
            $this->is_default = $language->is_default;
            $this->is_active = $language->is_active;
            $this->sort_order = $language->sort_order;
            $this->translations = $language->translations
                ? json_encode($language->translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : '';
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'native_name' => ['nullable', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', Rule::unique('languages', 'code')->ignore($this->languageId)],
            'flag' => ['nullable', 'string', 'max:5', 'alpha'],
            'is_rtl' => ['boolean'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'translations' => ['nullable', 'json'],
        ];
    }

    public function save()
    {
        $this->validate();

        // Active is implied by being the default language.
        if ($this->is_default) {
            $this->is_active = true;
        }

        $translations = $this->translations !== '' ? json_decode($this->translations, true) : null;

        DB::transaction(function () use ($translations): void {
            if ($this->isEditing) {
                $language = Language::findOrFail($this->languageId);
                $before = $language->getOriginal();

                if ($this->is_default) {
                    Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
                }

                $language->update([
                    'name' => $this->name,
                    'native_name' => $this->native_name ?: null,
                    'code' => $this->code,
                    'flag' => $this->flag ? strtolower($this->flag) : null,
                    'is_rtl' => $this->is_rtl,
                    'is_default' => $this->is_default,
                    'is_active' => $this->is_active,
                    'sort_order' => $this->sort_order,
                    'translations' => $translations,
                ]);

                $changes = $this->auditDiff($language, $before);
                if ($changes !== []) {
                    $this->logActivity(ActivityModule::Language, ActivityAction::Updated, $language, $changes);
                }
            } else {
                if ($this->is_default) {
                    Language::query()->update(['is_default' => false]);
                }

                $language = Language::create([
                    'name' => $this->name,
                    'native_name' => $this->native_name ?: null,
                    'code' => $this->code,
                    'flag' => $this->flag ? strtolower($this->flag) : null,
                    'is_rtl' => $this->is_rtl,
                    'is_default' => $this->is_default,
                    'is_active' => $this->is_active,
                    'sort_order' => $this->sort_order,
                    'translations' => $translations,
                ]);

                $this->logActivity(ActivityModule::Language, ActivityAction::Created, $language, [
                    'attributes' => $language->only(['name', 'code', 'is_default', 'is_active']),
                ]);
            }
        });

        return $this->redirectWithSuccess(
            "{$this->name} language ".($this->isEditing ? 'updated' : 'created').' successfully.',
        );
    }

    public function render(): View
    {
        return view('livewire.admin.application.language.form');
    }
}
