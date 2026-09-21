<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a Socialite provider's email for the calling identity is
 * unverified, no account has ever linked this exact provider id before, and
 * the email nonetheless matches an existing app account — auto-linking or
 * merging here would let anyone claiming that email take over the matching
 * account. Caught by whichever controller triggered it to build a 422
 * PROVIDER_EMAIL_UNVERIFIED response.
 */
class ProviderEmailUnverifiedException extends RuntimeException {}
