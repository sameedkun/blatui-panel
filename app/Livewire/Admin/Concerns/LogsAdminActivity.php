<?php

namespace App\Livewire\Admin\Concerns;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin convenience layer over {@see ActivityLogger} for admin Livewire
 * components. The causer resolves from auth() (the acting admin) and the context
 * is auto-detected (Admin); use ActivityLogger directly when an explicit or
 * system causer/context is needed.
 */
trait LogsAdminActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    protected function logActivity(ActivityModule $module, ActivityAction $action, ?Model $subject = null, array $properties = []): void
    {
        ActivityLogger::log($module, $action, $subject, $properties);
    }

    /**
     * @param  array<string, mixed>  $before  Pre-update attributes ($model->getOriginal()).
     * @param  array<int, string>  $extraExcept
     * @return array<string, mixed>
     */
    protected function auditDiff(Model $model, array $before, array $extraExcept = []): array
    {
        return ActivityLogger::diff($model, $before, $extraExcept);
    }
}
