<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Http\Controllers\AuthenticateUsingPasskeyController as BaseAuthenticateUsingPasskeyController;
use Spatie\LaravelPasskeys\Http\Requests\AuthenticateUsingPasskeysRequest;
use Spatie\LaravelPasskeys\Support\Config;

/**
 * Fixes a session-key mismatch in spatie/laravel-passkeys 1.8.1: its own
 * GeneratePasskeyAuthenticationOptionsController flashes the challenge under
 * `passkey-registration-options`, while its AuthenticateUsingPasskeyController
 * reads `passkey-authentication-options` — the keys never match, so a passkey
 * login always falls through to "Could not login using the given passkey."
 *
 * This overrides __invoke() to read the key PasskeyAuthenticationOptionsController
 * actually writes; everything else (find_passkey action, login, the
 * PasskeyUsedToAuthenticateEvent, redirect) is unchanged, reusing the parent's
 * protected helpers.
 */
class AuthenticateUsingPasskeyController extends BaseAuthenticateUsingPasskeyController
{
    public function __invoke(AuthenticateUsingPasskeysRequest $request)
    {
        $passkeyOptions = Session::pull('passkeys.authentication-options');

        if (blank($passkeyOptions)) {
            return $this->invalidPasskeyResponse();
        }

        $findAuthenticatableUsingPasskey = Config::getAction(
            'find_passkey',
            FindPasskeyToAuthenticateAction::class
        );

        $passkey = $findAuthenticatableUsingPasskey->execute(
            $request->input('start_authentication_response'),
            $passkeyOptions,
        );

        if (! $passkey) {
            return $this->invalidPasskeyResponse();
        }

        $authenticatable = $passkey->authenticatable;

        if (! $authenticatable) {
            return $this->invalidPasskeyResponse();
        }

        $this->logInAuthenticatable($authenticatable, $request->boolean('remember'));

        $this->firePasskeyEvent($passkey, $request);

        return $this->validPasskeyResponse($request);
    }
}
