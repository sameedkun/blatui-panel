<?php

namespace App\Http\Resources\V1;

use App\Models\Language;
use Illuminate\Http\Request;

/**
 * @mixin Language
 */
class LanguageDetailResource extends LanguageResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'translations' => $this->translations,
        ];
    }
}
