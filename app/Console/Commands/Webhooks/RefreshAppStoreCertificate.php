<?php

namespace App\Console\Commands\Webhooks;

use App\Services\Webhooks\AppStore\RootCertificate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Run once on first deploy (App Store notifications are rejected until the
 * certificate exists), then monthly from the scheduler. A failed refresh
 * leaves the current certificate in place.
 */
#[Signature('app-store:refresh-certificate')]
#[Description('Download and verify the Apple Root CA - G3 certificate used to verify App Store notifications')]
class RefreshAppStoreCertificate extends Command
{
    public function handle(RootCertificate $certificate): int
    {
        try {
            $fingerprint = $certificate->refresh();
        } catch (RuntimeException $e) {
            Log::channel('webhooks')->error('App Store: root certificate refresh failed', ['error' => $e->getMessage()]);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Apple root certificate saved to [{$certificate->path()}].");
        $this->components->twoColumnDetail('SHA-256', strtoupper($fingerprint));

        return self::SUCCESS;
    }
}
