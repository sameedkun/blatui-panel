<?php

namespace App\Support\Dashboard\Reports;

/**
 * One optional select filter on a report ("Plan: All ▾"). Every filter has an
 * implicit "All" — an empty or unknown value means "don't filter".
 */
final class ReportFilter
{
    /**
     * @param  array<string, string>  $options  value => label
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $options,
    ) {}

    /** @param  array<string|int, string>  $options  value => label */
    public static function select(string $key, string $label, array $options): self
    {
        return new self($key, $label, collect($options)->mapWithKeys(fn (string $label, string|int $value): array => [(string) $value => $label])->all());
    }

    public function accepts(mixed $value): bool
    {
        return is_scalar($value) && array_key_exists((string) $value, $this->options);
    }
}
