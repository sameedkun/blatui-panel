<?php

namespace App\Exceptions;

use App\Services\Device\DeviceService;
use RuntimeException;

/**
 * Thrown by {@see DeviceService::register()} when a user is
 * already at their plan's concurrent device limit and the incoming device is
 * new (or reactivating from revoked) — re-login on an already-active device
 * never triggers this.
 */
class DeviceLimitExceededException extends RuntimeException {}
