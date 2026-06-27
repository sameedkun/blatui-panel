<?php

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin.guest')]
#[Title('Login')]
class Login extends Component
{
    public function render()
    {
        return view('livewire.auth.login');
    }
}
