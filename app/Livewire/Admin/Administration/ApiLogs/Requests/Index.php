<?php

namespace App\Livewire\Admin\Administration\ApiLogs\Requests;

use App\Enum\UserType;
use App\Livewire\Admin\BaseIndex;
use App\Models\ApiLog\ApiRequestLog;
use App\Support\ApiLogs\ApiLogAnalytics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

/**
 * The raw API request log (7-day retention), newest first. Defaults to the
 * last 24 hours so the common "what just happened" lookup never scans a
 * week of rows. Searching for an exact request ID jumps straight to that
 * request's detail page — the way an app developer's bug report arrives.
 *
 * Deep links from elsewhere (a request's detail page, the analytics tables)
 * pre-fill filters via query string: ?correlation=, ?ip=, ?user_id=, ?route=.
 */
#[Layout('layouts.admin.app')]
class Index extends BaseIndex
{
    public int $perPage = 25;

    public string $sortBy = 'created_at';

    public bool $live = false;

    /** @var list<string> */
    private const array SORTABLE = ['created_at', 'duration_ms', 'status_code'];

    /** @var list<string> */
    private const array METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function mount(): void
    {
        $this->authorize('api_logs.requests.view');

        $this->filters = $this->defaultFilters();

        $deepLinked = false;

        foreach (['correlation', 'ip', 'user_id', 'route'] as $key) {
            $value = request()->query($key);

            if (is_string($value) && $value !== '') {
                $this->filters[$key] = $value;
                $deepLinked = true;
            }
        }

        // A correlation, IP or user can span more than the default day.
        if ($deepLinked) {
            $this->filters['period'] = '7d';
        }
    }

    public function updatedSearch(): void
    {
        $term = trim($this->search);

        if (preg_match('/^req_[0-9A-Z]{26}$/i', $term) === 1 && ApiRequestLog::where('request_id', $term)->exists()) {
            $this->redirectRoute('admin.api-logs.requests.show', ['requestId' => $term], navigate: true);

            return;
        }

        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (in_array($column, self::SORTABLE, true)) {
            parent::sort($column);
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filters = $this->defaultFilters();
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filters != $this->defaultFilters();
    }

    public function toggleLive(): void
    {
        $this->live = ! $this->live;
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    private function defaultFilters(): array
    {
        return [
            'period' => '24h',
            'date_from' => '',
            'date_to' => '',
            'method' => [],
            'status_class' => [],
            'status_code' => '',
            'route' => '',
            'version' => '',
            'user' => '',
            'user_id' => '',
            'user_type' => '',
            'ip' => '',
            'min_duration' => '',
            'has_exception' => '',
            'error_code' => '',
            'client_type' => '',
            'correlation' => '',
        ];
    }

    protected function baseQuery(): Builder
    {
        return ApiRequestLog::query()
            ->select([
                'id', 'request_id', 'correlation_id', 'method', 'path', 'route_uri', 'api_version',
                'status_code', 'status_class', 'duration_ms', 'ip', 'user_id', 'user_type',
                'error_code', 'has_exception', 'sample_weight', 'created_at',
            ])
            ->with('user:id,name,email,type');
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $q) => $q
            ->where('request_id', $term)
            ->orWhere('correlation_id', $term)
            ->orWhere('ip', $term)
            ->orWhere('path', 'like', $like));
    }

    protected function filterConfig(): array
    {
        return [
            'period' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('created_at', '>=', match ($v) {
                '15m' => now()->subMinutes(15),
                '1h' => now()->subHour(),
                '7d' => now()->subDays(7),
                default => now()->subDay(),
            })],
            'date_from' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('created_at', '>=', now()->parse($v)->startOfDay())],
            'date_to' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('created_at', '<=', now()->parse($v)->endOfDay())],
            'method' => ['apply' => fn (Builder $q, array $v): Builder => $q->whereIn('method', $v)],
            'status_class' => ['apply' => fn (Builder $q, array $v): Builder => $q->whereIn('status_class', array_map('intval', $v))],
            'status_code' => ['apply' => fn (Builder $q, string $v): Builder => is_numeric($v) ? $q->where('status_code', (int) $v) : $q],
            'route' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('route_uri', $v)],
            'version' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('api_version', $v)],
            'user' => ['apply' => fn (Builder $q, string $v): Builder => $q->whereIn(
                'user_id',
                fn ($users) => $users->select('id')->from('users')->where('email', 'like', '%'.addcslashes($v, '%_\\').'%'),
            )],
            'user_id' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('user_id', (int) $v)],
            'user_type' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('user_type', $v)],
            'ip' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('ip', trim($v))],
            'min_duration' => ['apply' => fn (Builder $q, string $v): Builder => is_numeric($v) ? $q->where('duration_ms', '>=', (float) $v) : $q],
            'has_exception' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('has_exception', true)],
            'error_code' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('error_code', trim($v))],
            'client_type' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('client_type', $v)],
            'correlation' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('correlation_id', trim($v))],
        ];
    }

    protected function filterBarConfig(): array
    {
        return [
            'period' => ['label' => __('api_logs.filters.period'), 'type' => 'select', 'options' => __('api_logs.periods')],
            'dates' => ['label' => __('api_logs.filters.dates'), 'type' => 'date-range', 'from_key' => 'date_from', 'to_key' => 'date_to'],
            'method' => ['label' => __('api_logs.filters.method'), 'type' => 'multi-select', 'options' => array_combine(self::METHODS, self::METHODS)],
            'status_class' => ['label' => __('api_logs.filters.status_class'), 'type' => 'multi-select', 'options' => ['2' => '2xx', '3' => '3xx', '4' => '4xx', '5' => '5xx']],
            'status_code' => ['label' => __('api_logs.filters.status_code'), 'type' => 'text', 'placeholder' => '422'],
            'route' => ['label' => __('api_logs.filters.route'), 'type' => 'select', 'options' => $this->routeOptions()],
            'version' => ['label' => __('api_logs.filters.version'), 'type' => 'select', 'options' => $this->versionOptions()],
            'user' => ['label' => __('api_logs.filters.user'), 'type' => 'text', 'placeholder' => 'jane@example.com'],
            'user_type' => [
                'label' => __('api_logs.filters.user_type'),
                'type' => 'select',
                'options' => collect(UserType::cases())->mapWithKeys(fn (UserType $type): array => [$type->value => __("enums.user_type.{$type->name}")])->all(),
            ],
            'ip' => ['label' => __('api_logs.filters.ip'), 'type' => 'text', 'placeholder' => '203.0.113.1'],
            'correlation' => ['label' => __('api_logs.filters.correlation'), 'type' => 'text', 'placeholder' => 'cor_…'],
            'error_code' => ['label' => __('api_logs.filters.error_code'), 'type' => 'text', 'placeholder' => 'DEVICE_BLOCKED'],
            'min_duration' => ['label' => __('api_logs.filters.min_duration'), 'type' => 'text', 'placeholder' => '1000'],
            'client_type' => ['label' => __('api_logs.filters.client_type'), 'type' => 'select', 'options' => __('api_logs.client_types')],
            'has_exception' => ['label' => __('api_logs.filters.has_exception'), 'type' => 'toggle', 'active_value' => '1', 'icon' => 'bug'],
        ];
    }

    /**
     * Endpoints seen within raw retention. Cached briefly — it's a DISTINCT
     * over the whole table, and the list barely changes minute to minute.
     *
     * @return array<string, string>
     */
    private function routeOptions(): array
    {
        return Cache::remember('api_logs:filter_routes', 300, fn (): array => ApiRequestLog::query()
            ->distinct()
            ->orderBy('route_uri')
            ->limit(300)
            ->pluck('route_uri')
            ->mapWithKeys(fn (string $uri): array => [$uri => $uri === ApiRequestLog::UNMATCHED_ROUTE ? __('api_logs.unmatched_route') : '/'.$uri])
            ->all());
    }

    /** @return array<string, string> */
    private function versionOptions(): array
    {
        return Cache::remember('api_logs:filter_versions', 300, fn (): array => ApiRequestLog::query()
            ->whereNotNull('api_version')
            ->distinct()
            ->orderBy('api_version')
            ->pluck('api_version', 'api_version')
            ->all());
    }

    protected function statsConfig(): array
    {
        return [
            [
                'label' => __('api_logs.stats.requests'),
                'value' => fn (): int => $this->dayKpis()['requests'],
                'icon' => 'activity',
                'description' => __('api_logs.stats.requests_hint'),
            ],
            [
                'label' => __('api_logs.stats.error_rate'),
                'value' => fn (): string => $this->dayKpis()['rate_5xx'].'%',
                'icon' => 'server-crash',
                'description' => __('api_logs.stats.error_rate_hint', ['count' => number_format($this->dayKpis()['errors_5xx'])]),
            ],
            [
                'label' => __('api_logs.stats.p95'),
                'value' => fn (): string => $this->dayKpis()['p95_ms'] === null ? '—' : __('api_logs.ms', ['value' => number_format($this->dayKpis()['p95_ms'])]),
                'icon' => 'timer',
                'description' => __('api_logs.stats.p95_hint'),
            ],
            [
                'label' => __('api_logs.stats.exceptions'),
                'value' => fn (): int => ApiRequestLog::query()->where('has_exception', true)->where('created_at', '>=', now()->subDay())->count(),
                'icon' => 'bug',
                'description' => __('api_logs.stats.exceptions_hint'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function dayKpis(): array
    {
        return once(function (): array {
            $analytics = app(ApiLogAnalytics::class);

            return $analytics->kpis($analytics->range('24h'));
        });
    }

    public function render(): View
    {
        return view('livewire.admin.administration.api-logs.requests.index', [
            'requests' => $this->getRecords(),
            'stats' => $this->resolveStats(),
        ])->title(__('api_logs.requests.title').' · '.__('api_logs.title'));
    }
}
