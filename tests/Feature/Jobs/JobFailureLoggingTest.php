<?php

namespace Tests\Feature\Jobs;

use App\Jobs\Account\PurgeExpiredAccounts;
use App\Jobs\Activity\ExportActivityLog;
use App\Jobs\Auth\PruneExpiredBlockedIps;
use App\Jobs\Auth\RecordBlockedIpHit;
use App\Jobs\Device\PruneRevokedDevices;
use App\Jobs\Device\ResolveDeviceLocation;
use App\Jobs\Notification\SendPushNotification;
use App\Jobs\Subscription\SyncSubscriptionStatuses;
use App\Jobs\Ticket\CloseInactiveTickets;
use App\Jobs\Ticket\PurgeClosedTickets;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class JobFailureLoggingTest extends TestCase
{
    #[DataProvider('jobs')]
    public function test_a_failed_job_is_logged_to_the_jobs_channel(object $job, string $jobName): void
    {
        $exception = new RuntimeException('Unexpected job failure.');
        $logger = Mockery::mock(LoggerInterface::class);

        Log::shouldReceive('channel')
            ->once()
            ->with('jobs')
            ->andReturn($logger);

        $logger->shouldReceive('error')
            ->once()
            ->with('Job failed: '.$jobName, [
                'job' => $job::class,
                'exception' => $exception,
            ]);

        $job->failed($exception);
    }

    /** @return array<string, array{0: object, 1: string}> */
    public static function jobs(): array
    {
        return [
            'auto close inactive tickets' => [new CloseInactiveTickets, 'CloseInactiveTickets'],
            'export activity log' => [new ExportActivityLog([]), 'ExportActivityLog'],
            'prune expired blocked IPs' => [new PruneExpiredBlockedIps, 'PruneExpiredBlockedIps'],
            'prune revoked devices' => [new PruneRevokedDevices, 'PruneRevokedDevices'],
            'purge closed tickets' => [new PurgeClosedTickets, 'PurgeClosedTickets'],
            'purge expired accounts' => [new PurgeExpiredAccounts, 'PurgeExpiredAccounts'],
            'record blocked IP hit' => [new RecordBlockedIpHit('203.0.113.1', null), 'RecordBlockedIpHit'],
            'resolve device location' => [new ResolveDeviceLocation(1, '203.0.113.1'), 'ResolveDeviceLocation'],
            'send push notification' => [new SendPushNotification(1), 'SendPushNotification'],
            'sync subscription statuses' => [new SyncSubscriptionStatuses, 'SyncSubscriptionStatuses'],
        ];
    }
}
