<?php

namespace App\Support;

use App\Contracts\ProviderNotification;
use App\Enum\PaymentProvider;
use App\Models\Webhooks\AppleNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps a {@see PaymentProvider} to the Eloquent model backing its raw
 * webhook-notification table. Same shape as
 * {@see ActivityPresenter::subjectUrlResolvers()} — a class-keyed
 * registry instead of a per-provider if/elseif chain, so the admin panel
 * (index/show pages, the Subscription Show tab, {@see
 * \App\Models\SubscriptionReceipt::notification()}) never branches on
 * provider. Adding a new provider is one array line here plus its model —
 * zero changes anywhere else.
 */
class WebhookNotificationRegistry
{
    /** @return array<string, class-string<Model&ProviderNotification>> */
    public static function providers(): array
    {
        return [
            PaymentProvider::AppStore->value => AppleNotification::class,
        ];
    }

    /** @return class-string<Model&ProviderNotification>|null */
    public static function modelFor(PaymentProvider $provider): ?string
    {
        return static::providers()[$provider->value] ?? null;
    }

    public static function resolve(?PaymentProvider $provider, ?int $id): ?ProviderNotification
    {
        if (! $provider || ! $id) {
            return null;
        }

        $model = static::modelFor($provider);

        /** @var (Model&ProviderNotification)|null $record */
        $record = $model ? $model::find($id) : null;

        return $record;
    }
}
