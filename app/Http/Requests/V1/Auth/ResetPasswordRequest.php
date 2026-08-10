<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `email` is the value straight off the reset link's query string — per
     * UrlResolver::passwordResetUrl(), that's Crypt::encryptString()'d, not a
     * plain address, so it can't carry the `email` format rule. The
     * controller decrypts it before handing it to the password broker.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::default()],
        ];
    }
}
