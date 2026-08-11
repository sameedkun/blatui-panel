<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Guest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Deliberately has no `unique:users,email` rule — GuestConversionService::
 * convertBySelf()'s own validateEmail() already enforces that (with the
 * correct ->ignore($guest->id) exclusion) and throws its own
 * ValidationException, which renders identically via ApiExceptionRenderer.
 * Duplicating the rule here would just risk the two drifting apart.
 */
class ConvertGuestRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::default()],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
