<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Feedback;

use App\Enum\FeedbackType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * This route carries no auth-restricting middleware — it must work for both
 * a logged-in caller and a guest — so `email` is only required when there's
 * no token to resolve an account (and thus no email to fall back on) from.
 */
class StoreFeedbackRequest extends FormRequest
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
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['nullable', Rule::enum(FeedbackType::class)],
            'email' => [$this->user('sanctum') ? 'nullable' : 'required', 'email'],
        ];
    }
}
