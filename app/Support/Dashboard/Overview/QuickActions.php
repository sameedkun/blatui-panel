<?php

namespace App\Support\Dashboard\Overview;

use App\Support\Dashboard\Blocks\ActionList;
use App\Support\Dashboard\DateRange;
use Illuminate\Support\Facades\Route;

/**
 * Shortcuts to the panel's most common tasks, listed in
 * `config('dashboard.overview_actions')` so an application adds its own
 * without code. Each entry keeps its permission — the renderer hides what the
 * viewer is not allowed to do — and entries whose route is not registered
 * are skipped.
 */
class QuickActions extends OverviewWidget
{
    public function build(DateRange $range): ?ActionList
    {
        $list = ActionList::make(__('dashboard.actions.title'))->icon('zap');

        foreach ((array) config('dashboard.overview_actions', []) as $action) {
            if (! Route::has($action['route'])) {
                continue;
            }

            $list->action(
                label: __($action['label']),
                href: route($action['route'], $action['parameters'] ?? []),
                icon: $action['icon'] ?? 'arrow-right',
                description: isset($action['description']) ? __($action['description']) : null,
                permission: $action['permission'] ?? null,
            );
        }

        return $list->actions === [] ? null : $list;
    }
}
