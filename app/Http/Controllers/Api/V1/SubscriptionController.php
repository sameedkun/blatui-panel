<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\V1\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service subscription visibility — a user checking their own plan
 * status and billing history. Mutating a subscription (assign/cancel/
 * reactivate) has no API surface yet; that only happens through the admin
 * panel today, via App\Services\Subscription\SubscriptionService.
 */
class SubscriptionController extends ApiController
{
    /**
     * The subscription currently granting access, if any — same definition
     * User::activeSubscription() already uses (trialing/active/grace with a
     * still-future end, or cancelled-but-not-yet-lapsed).
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription()->with(['plan', 'planPrice'])->first();

        return $this->success([
            'subscription' => $subscription ? new SubscriptionResource($subscription) : null,
        ]);
    }

    /** Every subscription the user has ever had, most recent first. */
    public function history(Request $request): JsonResponse
    {
        $subscriptions = $request->user()->subscriptions()
            ->with(['plan', 'planPrice'])
            ->latest('starts_at')
            ->get();

        return $this->success([
            'subscriptions' => SubscriptionResource::collection($subscriptions),
        ]);
    }
}
