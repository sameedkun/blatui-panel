<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin.guest')]
#[Title('Login')]
class Login extends Component
{
    public ?string $email = '';

    public ?string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'email' => 'required|string',
            'password' => 'required|string',
            'remember' => 'boolean',
        ];
    }

    public function login()
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        // Must be staff AND able to access the panel.
        // super-admin passes the permission check via Gate::before / seeded perms.
        if (! $user->isStaff() || ! $user->can(config('panel.access')['admin'])) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Banned staff shouldn't get in either.
        if ($user->isBanned()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('auth.banned'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return redirect()->intended(route('admin.dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.login');
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }
}
