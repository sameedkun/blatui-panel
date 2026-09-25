<?php

namespace App\Livewire\Admin\Dashboard;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin.app')]
#[Title('Dashboard')]
class Index extends Component
{
    public function render(): View
    {
        return view('livewire.admin.dashboard.index');
    }
}
