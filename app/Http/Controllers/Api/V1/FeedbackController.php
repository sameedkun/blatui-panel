<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enum\FeedbackType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\V1\Feedback\StoreFeedbackRequest;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;

/**
 * Public feedback intake — works for both an authenticated caller and a
 * guest with no token at all. No auth-restricting middleware sits in front
 * of this route; the caller is resolved manually below so either path works.
 */
class FeedbackController extends ApiController
{
    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');

        Feedback::create([
            'user_id' => $user?->id,
            'email' => $user?->email ?? $request->validated('email'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'type' => $request->validated('type') ?? FeedbackType::General->value,
        ]);

        return $this->created(message: 'Thanks for your feedback.');
    }
}
