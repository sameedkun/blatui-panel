<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A provider webhook body that failed verification or couldn't be decoded —
 * not signed by the provider, malformed, or addressed to a different app. The
 * webhook controller answers 400 and stores nothing.
 */
class InvalidWebhookPayloadException extends RuntimeException {}
