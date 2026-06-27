<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasToast;
use Livewire\Component;

abstract class BaseForm extends Component
{
    use HasToast;

    public bool $isEditing = false;

    /** Named route to redirect to after a successful save. */
    abstract protected function indexRoute(): string;

    protected function redirectWithSuccess(string $message)
    {
        session()->flash('toast', ['type' => 'success', 'title' => $message]);

        return $this->redirect(route($this->indexRoute()));
    }
}
