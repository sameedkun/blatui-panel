<?php

namespace App\Livewire\Admin\Settings;

use App\Livewire\Admin\Concerns\HasToast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('livewire.admin.settings.index')]
#[Title('Settings')]
abstract class BaseSettings extends Component
{
    use HasToast;

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function save(): void
    {
        $this->authorize($this->editPermission());

        $this->validate();

        $this->saveSettings();

        $this->toastSuccess($this->successMessage());
    }

    /**
     * Load settings into component state.
     */
    abstract protected function loadSettings(): void;

    /**
     * Save/persist settings.
     */
    abstract protected function saveSettings(): void;

    /**
     * Validation rules for form inputs.
     */
    abstract protected function rules(): array;

    /**
     * Spatie permission required to save/edit settings.
     */
    abstract protected function editPermission(): string;

    /**
     * Custom toast success message.
     */
    abstract protected function successMessage(): string;
}
