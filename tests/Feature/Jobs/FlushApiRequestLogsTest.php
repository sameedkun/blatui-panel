<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ApiLog\FlushApiRequestLogs;
use App\Models\ApiLog\ApiRequestException;
use App\Models\ApiLog\ApiRequestLog;
use App\Models\ApiLog\ApiRequestPayload;
use App\Support\ApiLogs\ApiLogBuffer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class FlushApiRequestLogsTest extends TestCase
{
    use RefreshDatabase;

    private ApiLogBuffer $buffer;

    protected function setUp(): void
    {
        parent::setUp();

        // Stands in for Redis: requests push here instead of writing to the DB.
        $this->buffer = new class implements ApiLogBuffer
        {
            /** @var list<array<string, mixed>> */
            public array $items = [];

            public function push(array $record): void
            {
                $this->items[] = json_decode(json_encode($record), true);
            }

            public function pop(int $count): array
            {
                return array_splice($this->items, 0, $count);
            }
        };

        $this->app->instance(ApiLogBuffer::class, $this->buffer);
    }

    public function test_buffered_records_are_bulk_inserted_in_batches(): void
    {
        config(['api_logs.flush.batch_size' => 2]);
        Route::middleware('api')->get('api/testing/explode', fn () => throw new RuntimeException('boom'));

        $this->getJson('/api/v1/plans?locale=en');
        $this->getJson('/api/v1/plans');
        $this->getJson('/api/testing/explode');

        $this->assertSame(0, ApiRequestLog::count());
        $this->assertCount(3, $this->buffer->items);

        $flushed = app()->call([new FlushApiRequestLogs, 'handle']);

        $this->assertSame(3, $flushed);
        $this->assertSame([], $this->buffer->items);
        $this->assertSame(3, ApiRequestLog::count());
        $this->assertSame(2, ApiRequestPayload::count());
        $this->assertSame(1, ApiRequestException::count());
        $this->assertSame(['locale' => 'en'], ApiRequestPayload::whereNotNull('query')->firstOrFail()->query);
    }

    public function test_an_empty_buffer_flushes_nothing(): void
    {
        Bus::fake();

        $this->assertSame(0, app()->call([new FlushApiRequestLogs, 'handle']));
        Bus::assertNotDispatched(FlushApiRequestLogs::class);
    }

    public function test_a_run_that_hits_its_batch_cap_re_dispatches_itself_for_the_backlog(): void
    {
        config(['api_logs.flush.batch_size' => 1, 'api_logs.flush.max_batches' => 2]);

        $this->getJson('/api/v1/plans');
        $this->getJson('/api/v1/plans');
        $this->getJson('/api/v1/plans');

        Bus::fake();

        $this->assertSame(2, app()->call([new FlushApiRequestLogs, 'handle']));
        $this->assertCount(1, $this->buffer->items);
        Bus::assertDispatched(FlushApiRequestLogs::class);
    }

    public function test_a_run_that_drains_the_buffer_does_not_re_dispatch(): void
    {
        config(['api_logs.flush.batch_size' => 5, 'api_logs.flush.max_batches' => 2]);

        $this->getJson('/api/v1/plans');

        Bus::fake();

        $this->assertSame(1, app()->call([new FlushApiRequestLogs, 'handle']));
        Bus::assertNotDispatched(FlushApiRequestLogs::class);
    }

    /**
     * A sub-minute schedule would keep every `schedule:run` alive for the whole
     * minute (and hang forever under a frozen test clock).
     */
    public function test_the_api_log_jobs_are_scheduled_without_sub_minute_frequencies(): void
    {
        $events = collect(app(Schedule::class)->events())->keyBy('description');

        $this->assertSame('* * * * *', $events['api-logs-flush']->expression);
        $this->assertSame('5 * * * *', $events['api-logs-aggregate']->expression);
        $this->assertSame('30 3 * * *', $events['api-logs-prune']->expression);
        $this->assertTrue($events->every(fn ($event): bool => ! $event->isRepeatable()));
    }

    public function test_the_flush_command_reports_what_it_wrote(): void
    {
        $this->getJson('/api/v1/plans');

        $this->artisan('api-logs:flush')
            ->expectsOutputToContain('Flushed 1 API request log(s).')
            ->assertSuccessful();

        $this->assertSame(1, ApiRequestLog::count());
    }
}
