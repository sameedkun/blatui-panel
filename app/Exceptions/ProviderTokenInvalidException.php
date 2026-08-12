<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when Socialite's userFromToken() can't verify a client-supplied
 * provider token (expired, revoked, malformed, or the provider's own
 * endpoint rejected it) — caught by whichever controller called it to build
 * a 422 PROVIDER_TOKEN_INVALID response.
 */
class ProviderTokenInvalidException extends RuntimeException {}
