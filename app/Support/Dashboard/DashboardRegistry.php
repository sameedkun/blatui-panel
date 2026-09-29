<?php

namespace App\Support\Dashboard;

use App\Models\User;
use App\Support\Dashboard\Analytics\AnalyticsSection;
use App\Support\Dashboard\Contracts\Widget;
use App\Support\Dashboard\Reports\ReportDefinition;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The dashboard's single source of what exists: Overview widgets per slot,
 * Analytics sections, and Report definitions — all read from
 * `config/dashboard.php` and resolved through the container.
 *
 * This is the seam that keeps the core dashboard product-agnostic: an
 * application adds its own analytics by appending classes to that config,
 * and every page picks them up without a single line of page code changing.
 * Everything handed to a viewer is filtered by the permission the class
 * itself declares.
 */
class DashboardRegistry
{
    /** Overview slots, in the order (and visual weight) they render. */
    public const array OVERVIEW_SLOTS = ['kpis', 'trends', 'status', 'activity', 'actions', 'health'];

    /** @var array<class-string, object> */
    private array $instances = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Overview widgets per slot, filtered to what the viewer may see.
     *
     * @return array<string, list<Widget>>
     */
    public function overview(?User $viewer = null): array
    {
        $slots = [];

        foreach (self::OVERVIEW_SLOTS as $slot) {
            $slots[$slot] = array_values(array_filter(
                array_map(fn (string $class): Widget => $this->resolve($class, Widget::class), (array) config("dashboard.overview.{$slot}", [])),
                fn (Widget $widget): bool => $this->permits($viewer, $widget->permission()),
            ));
        }

        return $slots;
    }

    /**
     * Analytics sections keyed by tab key, filtered to what the viewer may see.
     *
     * @return array<string, AnalyticsSection>
     */
    public function sections(?User $viewer = null): array
    {
        $sections = [];

        foreach ((array) config('dashboard.analytics', []) as $class) {
            $section = $this->resolve($class, AnalyticsSection::class);

            if ($this->permits($viewer, $section->permission())) {
                $sections[$section->key()] = $section;
            }
        }

        return $sections;
    }

    /**
     * Report definitions keyed by report key, filtered to what the viewer may see.
     *
     * @return array<string, ReportDefinition>
     */
    public function reports(?User $viewer = null): array
    {
        $reports = [];

        foreach ((array) config('dashboard.reports.definitions', []) as $class) {
            $report = $this->resolve($class, ReportDefinition::class);

            if ($this->permits($viewer, $report->permission())) {
                $reports[$report->key()] = $report;
            }
        }

        return $reports;
    }

    /** One report definition by key, unfiltered (queued jobs run without a viewer). */
    public function report(string $key): ?ReportDefinition
    {
        return $this->reports()[$key] ?? null;
    }

    public function permits(?User $viewer, ?string $permission): bool
    {
        return $viewer === null || $permission === null || $viewer->can($permission);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    private function resolve(string $class, string $contract): object
    {
        $instance = $this->instances[$class] ??= $this->container->make($class);

        if (! $instance instanceof $contract) {
            throw new InvalidArgumentException(sprintf('Dashboard entry [%s] must implement or extend [%s].', $class, $contract));
        }

        return $instance;
    }
}
