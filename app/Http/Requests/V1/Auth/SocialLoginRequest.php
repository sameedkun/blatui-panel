<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use App\Enum\DeviceType;
use App\Http\Requests\V1\Concerns\DetectsBrowserClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `token` is the provider's own token — an OAuth access token for Google, an
 * identity (id_token) JWT for Apple — verified server-side in
 * SocialController::login() via Socialite::userFromToken(). Device rules are
 * identical to LoginRequest's, since this is the same login/signup entry
 * point, just authenticated by a provider token instead of a password.
 * `name` is only a fallback used when creating a brand-new account and
 * Socialite doesn't return one — Apple's id_token never carries a name
 * claim, so a client that collected one from Apple's native sign-in sheet
 * can still pass it through; ignored entirely when logging into an
 * existing account.
 */
class SocialLoginRequest extends FormRequest
{
    use DetectsBrowserClient;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'token' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isBrowserClient()) {
            return $rules;
        }

        return $rules + [
            'device' => ['required', 'array'],
            'device.fingerprint' => ['required', 'string'],
            'device.name' => ['nullable', 'string', 'max:255'],
            'device.model' => ['nullable', 'string', 'max:255'],
            'device.platform' => ['nullable', 'string', 'max:255'],
            'device.os' => ['nullable', 'string', 'max:255'],
            'device.type' => ['nullable', Rule::enum(DeviceType::class)],
            'device.app_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
