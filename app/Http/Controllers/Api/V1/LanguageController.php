<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\V1\LanguageDetailResource;
use App\Http\Resources\V1\LanguageResource;
use App\Models\Language;
use Illuminate\Http\JsonResponse;

/** Public language catalog — no auth required, reachable from a pre-login language picker. */
class LanguageController extends ApiController
{
    public function index(): JsonResponse
    {
        $languages = Language::active()->orderBy('sort_order')->orderBy('name')->get();

        return $this->success(LanguageResource::collection($languages));
    }

    /**
     * {language:code} binds on Language::code rather than the numeric id.
     * A retired (inactive) language 404s here too, same as it's excluded
     * from index() — never fetchable via a guessed code.
     */
    public function show(Language $language): JsonResponse
    {
        abort_unless($language->is_active, 404);

        return $this->success(new LanguageDetailResource($language));
    }
}
