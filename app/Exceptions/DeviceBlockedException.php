<?php

namespace App\Exceptions;

use App\Services\DeviceService;
use RuntimeException;

/**
 * Thrown by {@see DeviceService::register()} when the matched
 * device is blocked — a blocked device is never reactivated by logging in
 * again, only by an admin via {@see DeviceService::unblock()}.
 */
class DeviceBlockedException extends RuntimeException {}
