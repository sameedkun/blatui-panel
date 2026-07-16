<?php

namespace App\Support;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use Illuminate\Database\Eloquent\Model;

/**
 * The single home for writing audit activity. Callable from anywhere — Livewire
 * components (via the LogsAdminActivity trait), services, jobs, and API
 * controllers — so audit logic is never duplicated per call site.
 *
 * Activity is described on four orthogonal axes:
 *   - category (log_name)  → {@see ActivityLogName}, a tiny fixed set
 *   - module               → {@see ActivityModule}, grows per feature
 *   - action (event)       → {@see ActivityAction}, reusable verbs
 *   - context              → {@see ActivityContext}, the originating runtime
 *
 * Mapping to Spatie: `event` = action, `log_name` = category. `subject_type` /
 * `subject_id` are left to Spatie (the real model class), preserving the
 * polymorphic subject() relation and Activity::forSubject(). Module + context
 * live in `properties` — the canonical store, and the only one for subject-less
 * events (e.g. an account purge).
 */
class ActivityLogger
{
    /**
     * Write an audit entry. Only $module and $action are required.
     *
     * The $causer sentinel distinguishes two intents a plain ?Model cannot:
     *   - omitted / false → resolve the causer from auth() (terse admin call sites)
     *   - explicit null    → system action, guaranteed no causer (never re-resolved)
     *
     * $context is auto-detected from the runtime when null; pass an explicit
     * value for Scheduler/Queue/Webhook origins the runtime cannot infer.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        ActivityModule $module,
        ActivityAction $action,
        ?Model $subject = null,
        array $properties = [],
        Model|null|false $causer = false,
        ?ActivityContext $context = null,
        ActivityLogName $logName = ActivityLogName::Audit,
    ): void {
        $causer = $causer === false ? auth()->user() : $causer;
        $context ??= self::detectContext();

        $activity = activity($logName->value)->event($action->value);

        if ($causer instanceof Model) {
            $activity->causedBy($causer);
        }

        if ($subject !== null) {
            $activity->performedOn($subject);
        }

        // Module + context are canonical here and always win over caller keys.
        $properties = array_merge($properties, [
            'module' => $module->value,
            'context' => $context->value,
            ...self::requestMeta($context),
        ]);

        $activity
            ->withProperties(array_filter($properties, fn ($value): bool => $value !== null && $value !== []))
            ->log($action->value);
    }

    /**
     * IP + user-agent, captured only for contexts backed by a real inbound HTTP
     * request (Admin, Api, Webhook) — Console/Scheduler/Queue never have one.
     *
     * @return array{ip: ?string, user_agent: ?string}
     */
    protected static function requestMeta(ActivityContext $context): array
    {
        $hasRequest = in_array($context, [ActivityContext::Admin, ActivityContext::Api, ActivityContext::Webhook], true);

        return [
            'ip' => $hasRequest ? request()?->ip() : null,
            'user_agent' => $hasRequest ? request()?->userAgent() : null,
        ];
    }

    /**
     * Best-effort context detection from the current runtime. Console covers
     * plain commands, scheduler, and queue workers (all CLI); callers that need
     * to distinguish Scheduler/Queue/Webhook pass an explicit context.
     */
    protected static function detectContext(): ActivityContext
    {
        if (app()->runningInConsole()) {
            return ActivityContext::Console;
        }

        $request = request();

        return $request->is('api/*') || $request->expectsJson()
            ? ActivityContext::Api
            : ActivityContext::Admin;
    }

    /**
     * Build an old→new diff of changed, non-sensitive attributes for a model.
     *
     * Eloquent's finishSave() runs syncOriginal() after a write, so getOriginal()
     * returns the NEW values post-update. Capture the pre-update state
     * ($model->getOriginal()) BEFORE calling update()/save() and pass it as
     * $before; the new values come from getChanges(), which survives the save.
     *
     * @param  array<string, mixed>  $before  Pre-update attributes ($model->getOriginal()).
     * @param  array<int, string>  $extraExcept
     * @return array{attributes: array<string, mixed>, old: array<string, mixed>}|array{}
     */
    public static function diff(Model $model, array $before, array $extraExcept = []): array
    {
        $except = array_merge(
            ['password', 'remember_token', 'updated_at', 'created_at', 'slug', 'external_id'],
            $extraExcept,
        );

        $new = [];
        $old = [];

        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, $except, true)) {
                continue;
            }

            $new[$key] = $value;
            $old[$key] = $before[$key] ?? null;
        }

        return $new === [] ? [] : ['attributes' => $new, 'old' => $old];
    }
}
