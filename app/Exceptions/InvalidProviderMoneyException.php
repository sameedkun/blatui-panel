<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A provider sent money fields that can't be turned into a trustworthy
 * amount — a negative or fractional price, a price without a currency (or the
 * reverse), a currency outside ISO 4217, a refund percentage out of range.
 * Thrown at the provider normalisation boundary so nothing is ever recorded
 * from it; the webhook processor leaves the notification unprocessed instead.
 */
class InvalidProviderMoneyException extends RuntimeException {}
