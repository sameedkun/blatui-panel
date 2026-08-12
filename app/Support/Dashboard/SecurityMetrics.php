<?php

namespace App\Support\Dashboard;

use App\Enum\DeviceType;
use App\Models\BlockedIp;
use App\Models\UserDevice;
use App\Support\Dashboard\Concerns\BuildsTimeSeries;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Device fleet and IP-blocking analytics.
 *
 * Device status is derived from `revoked_at`/`blocked_at` rather than stored as a flag
 * (see {@see UserDevice}), so everything here goes through the model's own scopes instead
 * of re-deriving that rule — a device counted as "active" on the dashboard is active by
 * exactly the same definition the middleware enforces.
 */
class SecurityMetrics
{
    use BuildsTimeSeries;

    public function activeDevices(): int
    {
        return UserDevice::query()->active()->count();
    }

    public function blockedDevices(): int
    {
        return UserDevice::query()->blocked()->count();
    }

    public function revokedDevices(): int
    {
        return UserDevice::query()->revoked()->count();
    }

    /** New device registrations inside the window, with its change against the previous window. */
    public function newDevices(DateRange $range): array
    {
        $count = fn ($from, $to): int => UserDevice::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $current = $count($range->start(), $range->end());
        $previous = $count($range->previousStart(), $range->previousEnd());

        return [
            'value' => $current,
            'previous' => $previous,
            'change' => $this->percentChange($current, $previous),
        ];
    }

    /**
     * Active devices by form factor.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function deviceTypeBreakdown(): array
    {
        $counts = UserDevice::query()
            ->active()
            ->groupBy('device_type')
            ->select(['device_type', DB::raw('COUNT(*) as aggregate')])
            ->pluck('aggregate', 'device_type')
            ->all();

        $labels = [];
        $values = [];

        foreach (DeviceType::cases() as $type) {
            $labels[] = $type->label();
            $values[] = (int) ($counts[$type->value] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Active devices by operating system, busiest first.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function platformBreakdown(int $limit = 6): array
    {
        $rows = UserDevice::query()
            ->active()
            ->whereNotNull('platform')
            ->where('platform', '!=', '')
            ->groupBy('platform')
            ->select(['platform', DB::raw('COUNT(*) as aggregate')])
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->pluck('aggregate', 'platform')
            ->all();

        return [
            'labels' => array_keys($rows),
            'values' => array_map('intval', array_values($rows)),
        ];
    }

    public function activeBlocks(): int
    {
        return BlockedIp::query()->active()->count();
    }

    /** Blocks that apply to every user, not just one account — the high-blast-radius ones. */
    public function globalBlocks(): int
    {
        return BlockedIp::query()->active()->global()->count();
    }

    /** Total requests turned away by an active block. */
    public function blockedHits(): int
    {
        return (int) BlockedIp::query()->active()->sum('hits');
    }

    /**
     * The blocks actually catching traffic, by hit count.
     *
     * @return Collection<int, BlockedIp>
     */
    public function busiestBlocks(int $limit = 6)
    {
        return BlockedIp::query()
            ->with('user')
            ->active()
            ->where('hits', '>', 0)
            ->orderByDesc('hits')
            ->limit($limit)
            ->get();
    }

    /**
     * Fingerprints seen against more than one account — the account-sharing signal.
     *
     * Mirrors the query behind the Shared Fingerprints page so both surfaces agree on
     * what counts as shared.
     */
    public function sharedFingerprints(): int
    {
        return DB::table('user_devices')
            ->whereNotNull('device_fingerprint')
            ->groupBy('device_fingerprint')
            ->havingRaw('COUNT(DISTINCT user_id) > 1')
            ->select('device_fingerprint')
            ->get()
            ->count();
    }

    /**
     * Device registrations over the window.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function registrationSeries(DateRange $range): array
    {
        $buckets = $this->countByPeriod(UserDevice::query(), $range);

        return [
            'labels' => $this->bucketLabels($buckets, $range),
            'values' => array_values($buckets),
        ];
    }
}
