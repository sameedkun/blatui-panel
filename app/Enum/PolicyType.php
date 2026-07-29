<?php

namespace App\Enum;

use App\Models\Policy;

/**
 * Fixed set of legal documents the Settings > Policies page manages, keyed to
 * {@see Policy}'s `key` column. Closed vocabulary (not
 * admin-extensible) — adding a new policy (e.g. a refund policy) is a code
 * change: add a case here and every other piece (the settings form, its
 * validation, and the audit log presenter) picks it up automatically.
 */
enum PolicyType: string
{
    case Privacy = 'privacy';
    case Terms = 'terms';
    case Refund = 'refund';

    public function label(): string
    {
        return __("settings.enums.policy_types.{$this->value}");
    }
}
