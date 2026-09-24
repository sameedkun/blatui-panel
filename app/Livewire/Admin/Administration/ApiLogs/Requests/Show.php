<?php

namespace App\Livewire\Admin\Administration\ApiLogs\Requests;

use App\Enum\UserType;
use App\Livewire\Admin\BaseShow;
use App\Livewire\Admin\Concerns\HasShowTabs;
use App\Models\ApiLog\ApiRequestException;
use App\Models\ApiLog\ApiRequestLog;
use App\Models\ApiLog\ApiRequestPayload;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ActivityPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Spatie\Activitylog\Models\Activity;
use Throwable;
use UAParser\Parser;

/**
 * Everything recorded about one API request, addressed by its public
 * request id. Raw logs live 7 days but exception rows 30, so a request
 * whose raw row has been pruned still opens — in an "expired" mode showing
 * just the exceptions it raised.
 *
 * Headers and bodies (Request/Response tabs, "Copy as cURL") need
 * api_logs.requests.manage on top of view — they're sanitized, but still the
 * most sensitive thing the log holds.
 */
#[Layout('layouts.admin.app')]
class Show extends BaseShow
{
    use HasShowTabs;

    public string $requestId = '';

    public bool $expired = false;

    public function mount(string $requestId): void
    {
        $log = ApiRequestLog::query()->with('payload')->where('request_id', $requestId)->first();

        if ($log === null) {
            $exception = ApiRequestException::query()->where('request_id', $requestId)->orderBy('id')->first();

            abort_unless($exception !== null, 404);

            $this->expired = true;
        }

        $this->requestId = $requestId;
        $this->initShow($log ?? $exception);
    }

    protected function indexRoute(): string
    {
        return 'admin.api-logs.requests.index';
    }

    protected function title(): string
    {
        return $this->requestId;
    }

    protected function viewPermission(): ?string
    {
        return 'api_logs.requests.view';
    }

    protected function breadcrumbs(): array
    {
        return [
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => __('api_logs.title').' · '.__('api_logs.requests.title'), 'url' => route($this->indexRoute())],
            ['label' => $this->requestId],
        ];
    }

    public function log(): ?ApiRequestLog
    {
        return $this->record instanceof ApiRequestLog ? $this->record : null;
    }

    /** @return Collection<int, ApiRequestException> */
    public function exceptions(): Collection
    {
        return once(fn (): Collection => ApiRequestException::query()
            ->where('request_id', $this->requestId)
            ->orderBy('id')
            ->get());
    }

    protected function tabs(): array
    {
        $base = 'livewire.admin.administration.api-logs.requests.tabs.';
        $exceptionsTab = $this->exceptions()->isEmpty() ? [] : [
            'exceptions' => [
                'label' => __('api_logs.tabs.exceptions', ['count' => $this->exceptions()->count()]),
                'icon' => 'bug',
                'view' => $base.'exceptions',
                'data' => fn (): array => ['occurrences' => $this->otherOccurrences()],
            ],
        ];

        if ($this->expired) {
            return [
                'overview' => ['label' => __('api_logs.tabs.overview'), 'icon' => 'info', 'view' => $base.'expired'],
                ...$exceptionsTab,
            ];
        }

        return [
            'overview' => [
                'label' => __('api_logs.tabs.overview'),
                'icon' => 'info',
                'view' => $base.'overview',
                'data' => fn (): array => ['related' => $this->relatedRequests()],
            ],
            'request' => [
                'label' => __('api_logs.tabs.request'),
                'icon' => 'arrow-up-right',
                'view' => $base.'request',
                'permission' => 'api_logs.requests.manage',
                'data' => fn (): array => ['curl' => $this->curl()],
            ],
            'response' => [
                'label' => __('api_logs.tabs.response'),
                'icon' => 'arrow-down-left',
                'view' => $base.'response',
                'permission' => 'api_logs.requests.manage',
            ],
            'user' => [
                'label' => __('api_logs.tabs.user'),
                'icon' => 'user-round',
                'view' => $base.'user',
                'data' => fn (): array => $this->userContext(),
            ],
            'timeline' => [
                'label' => __('api_logs.tabs.timeline'),
                'icon' => 'list-tree',
                'view' => $base.'timeline',
                'data' => fn (): array => $this->timeline(),
            ],
            ...$exceptionsTab,
        ];
    }

    /** @return Collection<int, ApiRequestLog> other requests in the same correlation, in order */
    private function relatedRequests(): Collection
    {
        return ApiRequestLog::query()
            ->where('correlation_id', $this->log()->correlation_id)
            ->where('request_id', '!=', $this->requestId)
            ->orderBy('created_at')
            ->limit(25)
            ->get(['request_id', 'method', 'path', 'status_code', 'duration_ms', 'created_at']);
    }

    /**
     * @return array{user: ?User, profileUrl: ?string, device: ?UserDevice, agent: ?string}
     */
    private function userContext(): array
    {
        $log = $this->log();
        $user = $log->user_id ? User::withTrashed()->find($log->user_id) : null;

        return [
            'user' => $user,
            'profileUrl' => $user ? $this->profileUrl($user) : null,
            'device' => $log->device_id ? UserDevice::find($log->device_id) : null,
            'agent' => $this->parsedUserAgent($log->user_agent),
        ];
    }

    private function profileUrl(User $user): ?string
    {
        $viewer = auth()->user();

        return match ($user->type) {
            UserType::App => $viewer->can('users.manage') ? route('admin.users.show', $user) : null,
            UserType::Guest => $viewer->can('guests.manage') ? route('admin.guests.show', $user) : null,
            UserType::Staff => $viewer->can('staff.edit') ? route('admin.staff.edit', $user) : null,
        };
    }

    /** Display-only "Chrome 128 / macOS 14" from the raw User-Agent. */
    private function parsedUserAgent(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        try {
            $result = Parser::create()->parse($userAgent);

            $parts = array_filter([
                trim($result->ua->family.' '.$result->ua->major),
                trim($result->os->family.' '.$result->os->major),
            ], fn (string $part): bool => $part !== '' && ! str_starts_with($part, 'Other'));

            return $parts === [] ? null : implode(' / ', $parts);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{phases: list<array{key: string, at: float}>, total: float, slowQueries: list<array{sql: string, time_ms: float}>, activities: list<array<string, mixed>>}
     */
    private function timeline(): array
    {
        $log = $this->log();
        $timings = $log->timings ?? [];
        asort($timings);

        $activities = Activity::query()
            ->where('properties->request_id', $this->requestId)
            ->whereBetween('created_at', [$log->created_at->subMinute(), $log->created_at->addDay()])
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (Activity $activity): array => [
                ...ActivityPresenter::present($activity),
                'at' => $activity->created_at,
            ])
            ->all();

        return [
            'phases' => collect($timings)->map(fn ($at, string $key): array => ['key' => $key, 'at' => (float) $at])->values()->all(),
            'total' => max((float) $log->duration_ms, (float) (collect($timings)->max() ?? 0), 1.0),
            'slowQueries' => $log->payload?->slow_queries ?? [],
            'activities' => $activities,
        ];
    }

    /**
     * Other recent requests that raised the same exception (by fingerprint).
     *
     * @return array<string, Collection<int, ApiRequestException>>
     */
    private function otherOccurrences(): array
    {
        return $this->exceptions()
            ->pluck('fingerprint')
            ->unique()
            ->mapWithKeys(fn (string $fingerprint): array => [$fingerprint => ApiRequestException::query()
                ->where('fingerprint', $fingerprint)
                ->where('request_id', '!=', $this->requestId)
                ->latest('created_at')
                ->limit(5)
                ->get(['request_id', 'created_at', 'status_code'])])
            ->all();
    }

    /**
     * A replayable cURL command rebuilt from the sanitized log — redacted
     * values stay redacted, so it's a starting point, not a credential leak.
     */
    private function curl(): ?string
    {
        $log = $this->log();
        $payload = $log->payload;

        if (! $payload instanceof ApiRequestPayload) {
            return null;
        }

        $query = is_array($payload->query) && $payload->query !== [] ? '?'.http_build_query($payload->query) : '';
        $url = rtrim((string) config('app.url'), '/').$log->path.$query;
        $skip = ['host', 'content-length', 'cookie', 'connection', 'accept-encoding'];

        $lines = ["curl -X {$log->method} ".$this->shellQuote($url)];

        foreach ($payload->request_headers ?? [] as $name => $value) {
            if (! in_array($name, $skip, true)) {
                $lines[] = '-H '.$this->shellQuote($name.': '.(is_array($value) ? implode(', ', $value) : $value));
            }
        }

        if ($payload->request_body !== null && $payload->request_body !== []) {
            $body = is_string($payload->request_body)
                ? $payload->request_body
                : json_encode($payload->request_body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $lines[] = '--data-raw '.$this->shellQuote($body);
        }

        return implode(" \\\n  ", $lines);
    }

    private function shellQuote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }

    public function render(): View
    {
        return view('livewire.admin.administration.api-logs.requests.show', [
            'log' => $this->log(),
            'exceptions' => $this->exceptions(),
            'tabs' => $this->availableTabs(),
            'active' => $this->resolveActiveTab(),
        ])->title(Str::limit($this->requestId, 40).' · '.__('api_logs.title'));
    }
}
