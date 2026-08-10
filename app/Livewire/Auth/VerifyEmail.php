<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin.guest')]
#[Title('Verify Email')]
class VerifyEmail extends Component
{
    public bool $justVerified = false;

    public User $user;

    public function mount(string $id, string $hash): void
    {
        /** @var User $user */
        $this->user = Auth::user() ?: User::where('external_id', $id)->firstOrFail();

        abort_if(! $this->user || $this->user->external_id !== $id, 403);
        abort_unless(hash_equals(sha1($this->user->getEmailForVerification()), $hash), 403);

        if ($this->user->hasVerifiedEmail()) {
            return;
        }

        $this->user->markEmailAsVerified();

        event(new Verified($this->user));

        $this->justVerified = true;
    }

    public function render()
    {
        return view('livewire.auth.verify-email');
    }
}
