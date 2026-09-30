<?php

namespace App\Console\Commands\Webhooks;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/**
 * Prints the URL to paste into App Store Connect → App Information → App Store
 * Server Notifications (V2). The signature is relative (path + query only), so
 * it survives proxies/load balancers rewriting host or scheme; it is derived
 * from APP_KEY, so rotating the key means re-running this and updating Apple.
 */
#[Signature('app-store:webhook-url')]
#[Description('Print the signed App Store Server Notifications URL to configure in App Store Connect')]
class AppStoreWebhookUrl extends Command
{
    public function handle(): int
    {
        $path = config('services.app_store.verify_url_signature')
            ? URL::signedRoute('webhooks.appstore', absolute: false)
            : route('webhooks.appstore', absolute: false);

        $this->line(url($path));

        return self::SUCCESS;
    }
}
