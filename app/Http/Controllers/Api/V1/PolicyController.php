<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\V1\PolicyDetailResource;
use App\Http\Resources\V1\PolicyResource;
use App\Models\Policy;
use Illuminate\Http\JsonResponse;

/** Public legal documents (privacy/terms/refund) — no auth required. */
class PolicyController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(PolicyResource::collection(Policy::all()));
    }

    /** {policy:key} binds on Policy::key (privacy/terms/refund) rather than the numeric id. */
    public function show(Policy $policy): JsonResponse
    {
        return $this->success(new PolicyDetailResource($policy->load('activeVersion')));
    }
}
