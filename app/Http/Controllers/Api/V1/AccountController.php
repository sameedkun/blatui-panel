<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\V1\Account\RequestDeletionRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\Account\DeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Self-service account deletion — a grace-period request the user can still
 * cancel, never an instant purge (that stays admin-only, via
 * DeletionService::instantPurgeByAdmin() from the panel). Deliberately kept
 * out of ProfileController — the same reasoning that split Verification/
 * Password out of AuthController applies here: a distinct, higher-stakes
 * concern with its own request shape.
 */
class AccountController extends ApiController
{
    /** requestByUser() itself doesn't guard against a repeat call, so the active-state check lives here. */
    public function store(RequestDeletionRequest $request, DeletionService $deletions): JsonResponse
    {
        $user = $request->user();

        if ($user->lifecycleState() !== 'active') {
            return $this->error(
                'Account deletion cannot be requested in its current state.',
                Response::HTTP_CONFLICT,
                ['code' => 'DELETION_NOT_AVAILABLE'],
            );
        }

        $deletions->requestByUser($user, $request->validated('reason'));

        return $this->success(['user' => new UserResource($user->fresh())], 'Account deletion scheduled.');
    }

    /**
     * cancelByUser() itself throws (→ 403) when there's nothing pending, or
     * when the pending request was admin-initiated — a user can never
     * override that, so no extra guard is needed here.
     */
    public function cancel(Request $request, DeletionService $deletions): JsonResponse
    {
        $user = $request->user();

        $deletions->cancelByUser($user);

        return $this->success(['user' => new UserResource($user->fresh())], 'Account deletion cancelled.');
    }
}
