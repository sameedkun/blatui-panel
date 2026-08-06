<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use App\Enum\DeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public const CLIENT_TYPE_HEADER = 'X-Client-Type';

    public const WEB_CLIENT_TYPE = 'web';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * A browser client can't produce a stable device fingerprint itself —
     * AuthController resolves one server-side via BrowserDeviceResolver
     * instead, so the client.* fields below aren't expected from it. Read
     * directly off the header (not $this->validated(), which isn't
     * available yet while rules() itself is being built).
     */
    public function isBrowserClient(): bool
    {
        return $this->header(self::CLIENT_TYPE_HEADER) === self::WEB_CLIENT_TYPE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];

        if ($this->isBrowserClient()) {
            return $rules;
        }

        // Required for every non-browser client — EnsureDeviceIsValid
        // rejects every authenticated request until a device is registered
        // for the token that issued it.
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
