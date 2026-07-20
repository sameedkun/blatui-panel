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
        return 'General settings updated successfully.';
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

    public function render(): View
    {
        return view('livewire.admin.settings.general');
    }
}
