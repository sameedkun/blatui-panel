<?php

namespace App\Http\Resources\V1;

use App\Models\Policy;
use Illuminate\Http\Request;

/**
 * @mixin Policy
 */
class PolicyDetailResource extends PolicyResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->whenLoaded('activeVersion');

        return [
            ...parent::toArray($request),
            'version' => $version?->version,
            'content' => $version?->content,
            'published_at' => $version?->published_at?->toIso8601String(),
        ];
    }
}
