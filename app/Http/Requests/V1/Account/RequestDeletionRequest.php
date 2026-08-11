<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

class RequestDeletionRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
