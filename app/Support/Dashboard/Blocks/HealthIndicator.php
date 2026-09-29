<?php

namespace App\Support\Dashboard\Blocks;

/**
 * One line of the Overview's health snapshot — a value plus a verdict.
 *
 * Deliberately application-level (API error rate, failed logins, queue
 * failures), never infrastructure: CPU, disks and servers belong to whatever
 * monitoring the deployment already runs, not to a product dashboard.
 *
 * @phpstan-consistent-constructor
 */
class HealthIndicator extends Block
{
    public const string OK = 'ok';

    public const string WARNING = 'warning';

    public const string CRITICAL = 'critical';

    public const string UNKNOWN = 'unknown';

    public ?string $hint = null;

    public function __construct(
        public string $label,
        public string $value,
        public string $status = self::OK,
        public string $icon = 'activity',
    ) {}

    public static function make(string $label, string $value, string $status = self::OK, string $icon = 'activity'): static
    {
        return new static($label, $value, $status, $icon);
    }

    /**
     * Grade a value against warning/critical thresholds (higher is worse).
     */
    public static function grade(int|float|null $value, int|float $warning, int|float $critical): string
    {
        return match (true) {
            $value === null => self::UNKNOWN,
            $value >= $critical => self::CRITICAL,
            $value >= $warning => self::WARNING,
            default => self::OK,
        };
    }

    public function hint(?string $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    public function view(): string
    {
        return 'livewire.admin.dashboard.blocks.health-indicator';
    }
}
