<?php

namespace App\Contracts;

/**
 * Optional capability a {@see ProviderNotification} model may implement —
 * separate from that contract (not every provider needs it, and it isn't
 * presentation). Discovered via `instanceof` in the admin panel, not the
 * registry, so a provider without a processing pipeline yet simply doesn't
 * show a "Reprocess" action rather than needing a stub implementation.
 */
interface RedispatchableNotification
{
    /**
     * Re-fires the provider's own "webhook received" event using this row's
     * already-stored data — the same event a real inbound webhook controller
     * dispatches, so whatever listener eventually does the processing work
     * handles an admin-triggered replay identically to the original delivery.
     */
    public function redispatch(): void;
}
