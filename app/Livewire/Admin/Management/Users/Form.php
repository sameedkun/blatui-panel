<?php

namespace App\Livewire\Admin\Management\Users;

use App\Livewire\Admin\BaseForm;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    public ?int $userId = null;

    #[Validate]
    public string $name = '';

    #[Validate]
    public string $email = '';

    #[Validate]
    public string $password = '';

    public bool $autoVerifyEmail = false;

    public bool $forcePasswordReset = false;

    /** Whether the email was changed on edit — triggers the verify prompt. */
    public bool $emailChanged = false;

    protected function indexRoute(): string
    {
        return 'admin.users.index';
    }

    public function title(): string
    {
        return $this->isEditing ? 'Edit User' : 'Create User';
    }

    public function mount(?User $user = null): void
    {
        if ($user) {
            $this->isEditing = true;
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],

            'password' => [
                $this->isEditing ? 'nullable' : 'required',
                Password::defaults(),
            ],
        ];
    }

    public function updatedEmail(string $value): void
    {
        if ($this->isEditing) {
            $user = User::find($this->userId);
            $this->emailChanged = $user && $user->email !== $value;
        }
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name'  => $this->name,
            'email' => $this->email,
        ];

        if ($this->isEditing) {
            $user = User::findOrFail($this->userId);

            if (filled($this->password)) {
                $data['password'] = Hash::make($this->password);
                $data['password_changed_at'] = now();
            }

            if ($this->emailChanged) {
                $data['email_verified_at'] = null;
            }

            if ($this->forcePasswordReset) {
                $data['password_changed_at'] = null;
            }

            $user->update($data);

            return $this->redirectWithSuccess("{$user->name} updated successfully.");
        }

        $user = User::create([
            ...$data,
            'password' => Hash::make($this->password),
            'email_verified_at' => $this->autoVerifyEmail ? now() : null,
            'registration_date' => now(),
        ]);

        $user->assignRole(config('panel.app_user_role'));

        return $this->redirectWithSuccess("{$user->name} created successfully.");
    }

    public function render(): View
    {
        return view('livewire.admin.management.users.form');
    }
}
