<?php

namespace App\Console\Commands\ApiLogs;

use App\Services\ApiLog\AggregationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Throwable;

#[Signature('api-logs:aggregate {--from= : Rebuild complete buckets from this date/time} {--to= : Up to this date/time (default: now)}')]
#[Description('Roll API request logs up into hourly/daily/monthly stats (run a backfill with --from)')]
class AggregateApiLogs extends Command
{
    public function handle(AggregationService $aggregation): int
    {
        $from = $this->option('from');

        try {
            $rebuilt = $from === null
                ? $aggregation->aggregate()
                : $aggregation->rebuildRange(Date::parse($from), Date::parse($this->option('to') ?? 'now'));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Rebuilt %d hourly, %d daily and %d monthly bucket(s).',
            $rebuilt['hour'],
            $rebuilt['day'],
            $rebuilt['month'],
        ));

        return self::SUCCESS;
    }
}
