<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\V1\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

/** Public pricing catalog — no auth required, reachable before signup/login. */
class PlanController extends ApiController
{
    /** List active pricing plans. */
    public function index(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->with([
                'prices' => fn ($query) => $query->where('is_active', true),
                'prices.providers' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->get();

        return $this->success(PlanResource::collection($plans));
    }
}
