<?php

namespace App\Support;

use App\Jobs\ExportActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Builds the filtered activity-log query from a plain, serializable state array.
 *
 * Living here (rather than inside the Livewire component) lets the interactive
 * viewer and the queued {@see ExportActivityLog} share one definition
 * of "the current filtered set" — the exported file always matches the screen.
 *
 * @phpstan-type FilterState array{
 *     filters?: array<string, mixed>,
 *     search?: string,
 *     subject_type?: ?string,
 *     subject_id?: ?int,
 * }
 */
class ActivityLogQuery
{
    /**
     * @param  FilterState  $state
     */
    public static function build(array $state): Builder
    {
        $filters = $state['filters'] ?? [];
        $query = Activity::query();

        self::applySubject($query, $state);
        self::applySearch($query, (string) ($state['search'] ?? ''));
        self::applyFilters($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<Activity>  $query
     * @param  FilterState  $state
     */
    protected static function applySubject(Builder $query, array $state): void
    {
        $type = $state['subject_type'] ?? null;
        $id = $state['subject_id'] ?? null;

        if ($type !== null && $id !== null) {
            $query->where('subject_type', $type)->where('subject_id', $id);
        }
    }

    /** Free-text search spans the human description and the raw properties JSON. */
    protected static function applySearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('description', 'like', "%{$term}%")
                ->orWhere('properties', 'like', "%{$term}%");
        });
    }

    /**
     * @param  Builder<Activity>  $query
     * @param  array<string, mixed>  $filters
     */
    protected static function applyFilters(Builder $query, array $filters): void
    {
        if ($values = self::listFilter($filters, 'module')) {
            $query->whereIn('properties->module', $values);
        }

        if ($values = self::listFilter($filters, 'action')) {
            $query->whereIn('event', $values);
        }

        if ($values = self::listFilter($filters, 'context')) {
            $query->whereIn('properties->context', $values);
        }

        if ($values = self::listFilter($filters, 'category')) {
            $query->whereIn('log_name', $values);
        }

        if ($values = self::listFilter($filters, 'causer')) {
            $wantsSystem = in_array('system', $values, true);
            $userIds = array_filter($values, fn ($v) => $v !== 'system');

            $query->where(function (Builder $q) use ($wantsSystem, $userIds): void {
                if ($userIds) {
                    $q->orWhere(function (Builder $sub) use ($userIds): void {
                        $sub->where('causer_type', (new User)->getMorphClass())
                            ->whereIn('causer_id', $userIds);
                    });
                }
                if ($wantsSystem) {
                    $q->orWhereNull('causer_id');
                }
            });
        }

        match ($filters['range'] ?? '') {
            'today' => $query->where('created_at', '>=', now()->startOfDay()),
            '7d' => $query->where('created_at', '>=', now()->subDays(7)),
            '30d' => $query->where('created_at', '>=', now()->subDays(30)),
            default => null, // 'custom' is driven by date_from / date_to below
        };

        if ($from = ($filters['date_from'] ?? '')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = ($filters['date_to'] ?? '')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, mixed>
     */
    protected static function listFilter(array $filters, string $key): array
    {
        return array_values(array_filter((array) ($filters[$key] ?? [])));
    }
}
