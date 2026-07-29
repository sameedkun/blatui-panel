<?php

namespace App\Livewire\Admin\Settings;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin.app')]
class Index extends Component
{
    public function render(): View
    {
        return view('livewire.admin.settings.index')
            ->title(__('settings.title'));
    }
}
