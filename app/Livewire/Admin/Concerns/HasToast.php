<?php

namespace App\Livewire\Admin\Concerns;

trait HasToast
{
    public function toast(string $type, string $title, string $description = ''): void
    {
        $this->dispatch('toast', type: $type, title: $title, description: $description ?: null);
    }

    public function toastSuccess(string $title, string $description = ''): void
    {
        $this->toast('success', $title, $description);
    }

    public function toastError(string $title, string $description = ''): void
    {
        $this->toast('error', $title, $description);
    }

    public function toastWarning(string $title, string $description = ''): void
    {
        $this->toast('warning', $title, $description);
    }

    public function toastInfo(string $title, string $description = ''): void
    {
        $this->toast('info', $title, $description);
    }
}
