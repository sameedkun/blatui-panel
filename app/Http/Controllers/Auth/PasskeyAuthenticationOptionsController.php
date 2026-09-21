<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Support\Config;

/**
 * Replaces the package's own GeneratePasskeyAuthenticationOptionsController.
 * Registered under the same route name/path so the vendor `<x-authenticate-passkey />`
 * component's JS (which calls route('passkeys.authentication_options')) needs no changes.
 *
 * See AuthenticateUsingPasskeyController's docblock for why this exists: the
 * package's own pair of controllers write and read two different session keys,
 * so a passkey login never succeeds out of the box.
 */
class PasskeyAuthenticationOptionsController
{
    public function __invoke()
    {
        $action = Config::getAction('generate_passkey_authentication_options', GeneratePasskeyAuthenticationOptionsAction::class);

        $options = $action->execute();

        // put()+pull() rather than flash() — the challenge must survive however
        // long the browser's WebAuthn/password-manager UI takes, not just "one request".
        Session::put('passkeys.authentication-options', $options);

        return $options;
    }
}
