<?php

namespace App\Enum;

use App\Models\SubscriptionReceipt;
use Illuminate\Support\Str;

/**
 * The kind of provider event a {@see SubscriptionReceipt} records. Closed
 * vocabulary — adding a new type is a code change.
 */
enum ReceiptType: string
{
    case Initial = 'initial';
    case Renewal = 'renewal';
    case Restore = 'restore';
    case Refund = 'refund';
    case Cancellation = 'cancellation';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
