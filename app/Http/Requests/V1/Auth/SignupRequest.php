<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use App\Enum\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SignupRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Scoped to active app-user rows only — matching against a staff, guest, or
            // trashed row here would leak that account's existence/type to an
            // unauthenticated caller (see AuthController::signup()'s own doc note). The
            // users.email column still carries a global DB-level unique constraint, so a
            // collision with one of those excluded rows surfaces there instead, as a
            // UniqueConstraintViolationException the controller turns into the same
            // generic "already taken" response.
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')
                ->where(fn ($query) => $query->where('type', UserType::App->value)->whereNull('deleted_at'))],
            'password' => ['required', 'confirmed', Password::default()],
        ];
    }
}
