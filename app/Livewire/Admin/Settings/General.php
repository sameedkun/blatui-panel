<?php

namespace App\Livewire\Admin\Settings;

use Illuminate\View\View;

class General extends BaseSettings
{
    public string $site_name = '';

    public string $environment = '';

    public string $timezone = '';

    protected function editPermission(): string
    {
        return 'settings.edit';
    }

    protected function successMessage(): string
    {
        return __('settings.toasts.general_saved');
    }

    protected function loadSettings(): void
    {
        $this->site_name = config('app.name');
        $this->environment = app()->environment();
        $this->timezone = config('app.timezone');
    }

    protected function saveSettings(): void
    {
        // Stubbed for now
    }

    protected function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:100'],
            'environment' => ['required', 'string'],
            'timezone' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'site_name.required' => __('settings.validation.site_name_required'),
            'site_name.string' => __('settings.validation.site_name_invalid'),
            'site_name.max' => __('settings.validation.site_name_max', ['max' => 100]),
            'environment.required' => __('settings.validation.environment_required'),
            'environment.string' => __('settings.validation.environment_invalid'),
            'timezone.required' => __('settings.validation.timezone_required'),
            'timezone.string' => __('settings.validation.timezone_invalid'),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.settings.general')
            ->title(__('settings.pages.general_title'));
    }
}
