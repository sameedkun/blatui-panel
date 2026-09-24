<?php

namespace App\Console\Commands\ApiLogs;

use App\Jobs\ApiLog\FlushApiRequestLogs;
use App\Support\ApiLogs\ApiLogBuffer;
use App\Support\ApiLogs\ApiLogWriter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('api-logs:flush')]
#[Description('Drain buffered API request logs into the database now')]
class FlushApiLogs extends Command
{
    public function handle(ApiLogBuffer $buffer, ApiLogWriter $writer): int
    {
        $flushed = (new FlushApiRequestLogs)->handle($buffer, $writer);

        $this->components->info("Flushed {$flushed} API request log(s).");

        return self::SUCCESS;
    }
}
