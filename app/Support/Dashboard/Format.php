<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Number;

/**
 * Turns a raw dashboard value into display text.
 *
 * Blocks carry raw numbers plus a format name rather than pre-formatted
 * strings, so reports and exports can reuse the same values and the view
 * decides presentation in exactly one place.
 */
final class Format
{
    public const string NUMBER = 'number';

    public const string DECIMAL = 'decimal';

    public const string CURRENCY = 'currency';

    public const string PERCENT = 'percent';

    /** A value measured in minutes, rendered as "2h 14m". */
    public const string DURATION = 'duration';

    public const string TEXT = 'text';

    public static function value(int|float|string|null $value, string $format = self::NUMBER): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        return match ($format) {
            self::CURRENCY => self::currency((float) $value),
            self::PERCENT => rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%',
            self::DURATION => self::duration((float) $value),
            self::DECIMAL => number_format((float) $value, 2),
            self::TEXT => (string) $value,
            default => number_format((float) $value, fmod((float) $value, 1.0) === 0.0 ? 0 : 1),
        };
    }

    public static function currency(float $value, ?string $currency = null): string
    {
        $currency ??= (string) config('dashboard.currency', 'USD');

        return (string) Number::currency($value, in: $currency, locale: app()->getLocale());
    }

    /** Minutes → "3d 4h", "2h 14m", "45m", "< 1m". */
    public static function duration(float $minutes): string
    {
        if ($minutes < 1) {
            return __('dashboard.durations.under_minute');
        }

        $minutes = (int) round($minutes);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        return match (true) {
            $days > 0 => __('dashboard.durations.days_hours', ['days' => $days, 'hours' => $hours]),
            $hours > 0 => __('dashboard.durations.hours_minutes', ['hours' => $hours, 'minutes' => $mins]),
            default => __('dashboard.durations.minutes', ['minutes' => $mins]),
        };
    }

    /**
     * Percentage change between two periods, or null when there is no
     * meaningful baseline — growth from zero is not "+100%", it is undefined,
     * and the UI renders a dash rather than a misleading number.
     */
    public static function change(int|float|null $current, int|float|null $previous): ?float
    {
        if ($current === null || $previous === null || (float) $previous === 0.0) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    /** $part as a percentage of $total, 0 when there is no total. */
    public static function share(int|float $part, int|float $total): float
    {
        return (float) $total === 0.0 ? 0.0 : round(($part / $total) * 100, 1);
    }
}
