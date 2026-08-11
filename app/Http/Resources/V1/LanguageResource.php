<?php

namespace App\Http\Resources\V1;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Language
 */
class LanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'flag' => $this->flag,
            'flag_emoji' => $this->flagEmoji(),
            'is_rtl' => $this->is_rtl,
            'is_default' => $this->is_default,
        ];
    }
}
