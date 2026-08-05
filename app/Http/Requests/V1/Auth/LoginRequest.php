<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Auth;

use App\Enum\DeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],

            // The device this login is from — required since
            // EnsureDeviceIsValid rejects every authenticated request until
            // a device is registered for the token that issued it.
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
