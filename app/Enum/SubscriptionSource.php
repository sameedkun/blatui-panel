<?php

namespace App\Enum;

use App\Models\Subscription;

/**
 * Why a {@see Subscription} exists — orthogonal to `provider`, which names the
 * billing integration it runs through. An admin's free grant is
 * `provider = local, source = admin`; an App Store purchase is
 * `provider = appstore, source = purchase`. Only `Purchase` implies money:
 * every other source is free access and normally has no transactions at all.
 * Closed vocabulary — adding a new source is a code change.
 */
enum SubscriptionSource: string
{
    case Purchase = 'purchase';
    case Admin = 'admin';
    case Promotional = 'promotional';
    case Migration = 'migration';
    case System = 'system';

    public function label(): string
    {
        return __("enums.subscription_source.{$this->name}");
    }

    /** Free access given by someone rather than bought. */
    public function isGrant(): bool
    {
        return $this !== self::Purchase;
    }
}
