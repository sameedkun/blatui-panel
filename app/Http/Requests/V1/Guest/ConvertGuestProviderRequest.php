<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Guest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `token` is the provider's own token — an OAuth access token for Google, an
 * identity (id_token) JWT for Apple — handed to Socialite's userFromToken()
 * in GuestController::convertWithProvider(), which verifies it against the
 * provider directly. Nothing about identity (id/email/verification) is
 * trusted from the client; it's all read back from Socialite's response.
 * `name` is only a fallback for Apple, which sends the user's name in the
 * initial authorization POST body, never inside the id_token JWT itself —
 * Google always returns a name from the token, so this is unused there.
 */
class ConvertGuestProviderRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
