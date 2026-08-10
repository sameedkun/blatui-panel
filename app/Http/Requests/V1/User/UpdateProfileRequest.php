<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Both fields are `sometimes` — routed as PUT, but a caller sending only
     * `avatar` (or only `name`) still isn't forced to resend the other.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:60'],
            'avatar' => ['sometimes', 'image', 'max:2048'],
        ];
    }
}
