<?php

namespace App\Services\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction as BaseFindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;

/**
 * A verified passkey credential only proves the request holds the private key —
 * it says nothing about whether that account is still allowed into the panel.
 * Defense-in-depth: re-applies the same isStaff()/not-banned/panel-access checks
 * {@see Login::login()} enforces on a password login, so a
 * staff member who loses access (demoted, banned) can't bypass it with an old
 * passkey. Returning null here just makes the controller treat it as an invalid
 * passkey, same as a credential it couldn't verify at all.
 */
class FindPasskeyToAuthenticateAction extends BaseFindPasskeyToAuthenticateAction
{
    public function execute(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
    {
        $passkey = parent::execute($publicKeyCredentialJson, $passkeyOptionsJson);

        if (! $passkey || ! $this->authenticatableMayUsePasskey($passkey)) {
            return null;
        }

        return $passkey;
    }

    public function authenticatableMayUsePasskey(Passkey $passkey): bool
    {
        $authenticatable = $passkey->authenticatable;

        return $authenticatable instanceof User
            && $authenticatable->isStaff()
            && ! $authenticatable->isBanned()
            && $authenticatable->can(config('panel.access.admin'));
    }
}
