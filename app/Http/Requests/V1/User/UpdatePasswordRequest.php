<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The `sanctum` guard must be named explicitly — current_password
            // falls back to config('auth.defaults.guard') ("web") otherwise,
            // which is always a guest under this stateless, token-based API.
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::default()],
        ];
    }
}
