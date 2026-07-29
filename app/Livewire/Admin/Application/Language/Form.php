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

    protected function validationAttributes(): array
    {
        return [
            'name' => __('languages.validation_attributes.name'),
            'native_name' => __('languages.validation_attributes.native_name'),
            'code' => __('languages.validation_attributes.code'),
            'flag' => __('languages.validation_attributes.flag'),
            'is_rtl' => __('languages.validation_attributes.is_rtl'),
            'is_default' => __('languages.validation_attributes.is_default'),
            'is_active' => __('languages.validation_attributes.is_active'),
            'sort_order' => __('languages.validation_attributes.sort_order'),
            'translations' => __('languages.validation_attributes.translations'),
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('languages.validation.name_required'),
            'name.string' => __('languages.validation.name_invalid'),
            'name.max' => __('languages.validation.name_max', ['max' => 100]),
            'native_name.string' => __('languages.validation.native_name_invalid'),
            'native_name.max' => __('languages.validation.native_name_max', ['max' => 100]),
            'code.required' => __('languages.validation.code_required'),
            'code.string' => __('languages.validation.code_invalid'),
            'code.max' => __('languages.validation.code_max', ['max' => 10]),
            'code.unique' => __('languages.validation.code_unique'),
            'flag.string' => __('languages.validation.flag_invalid'),
            'flag.max' => __('languages.validation.flag_max', ['max' => 5]),
            'flag.alpha' => __('languages.validation.flag_alpha'),
            'is_rtl.boolean' => __('languages.validation.is_rtl_invalid'),
            'is_default.boolean' => __('languages.validation.is_default_invalid'),
            'is_active.boolean' => __('languages.validation.is_active_invalid'),
            'sort_order.integer' => __('languages.validation.sort_order_integer'),
            'sort_order.min' => __('languages.validation.sort_order_min'),
            'translations.json' => __('languages.validation.translations_json'),
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
            $this->isEditing
                ? __('languages.toasts.updated', ['name' => $this->name])
                : __('languages.toasts.created', ['name' => $this->name]),
        );
    }

    public function render(): View
    {
        return view('livewire.admin.application.language.form')
            ->title($this->isEditing ? __('languages.form.edit_title') : __('languages.form.create_title'));
    }
}
