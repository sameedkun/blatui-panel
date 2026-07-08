<?php

namespace App\Enum;

/**
 * Axis 1 — category (Spatie `log_name`). A tiny fixed set that never grows with
 * modules. Use {@see ActivityModule} for the per-feature axis instead.
 */
enum ActivityLogName: string
{
    case Audit = 'audit';
    case Authentication = 'authentication';
    case System = 'system';
}
