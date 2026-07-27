<?php

namespace App\Enum;

use App\Models\UserDevice;
use Illuminate\Support\Str;

/**
 * Coarse device form-factor. Kept separate from the free-text `platform`/`os`
 * columns on {@see UserDevice}, which already carry OS-level detail.
 */
enum DeviceType: string
{
    case Mobile = 'mobile';
    case Tablet = 'tablet';
    case Desktop = 'desktop';
    case Web = 'web';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
