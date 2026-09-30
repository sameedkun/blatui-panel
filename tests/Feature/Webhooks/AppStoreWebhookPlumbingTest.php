<?php

namespace Tests\Feature\Webhooks;

use App\Events\Webhooks\AppStoreWebhookReceived;
use App\Listeners\Webhooks\ProcessAppStoreNotification;
use App\Models\Webhooks\AppleNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class AppStoreWebhookPlumbingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_processing_listener_is_queued_and_discovered(): void
    {
        Event::fake();

        $this->assertContains(ShouldQueue::class, class_implements(ProcessAppStoreNotification::class));
        Event::assertListening(AppStoreWebhookReceived::class, ProcessAppStoreNotification::class);
    }

    public function test_a_failed_processing_job_is_logged_to_the_jobs_channel(): void
    {
        $notification = AppleNotification::factory()->create();
        $exception = new RuntimeException('Processing failed.');
        $logger = Mockery::mock(LoggerInterface::class);

        Log::shouldReceive('channel')->once()->with('jobs')->andReturn($logger);
        $logger->shouldReceive('error')->once()->with('Job failed: ProcessAppStoreNotification', [
            'job' => ProcessAppStoreNotification::class,
            'notification_id' => $notification->id,
            'exception' => $exception,
        ]);

        app(ProcessAppStoreNotification::class)->failed(new AppStoreWebhookReceived(
            $notification->notification_type,
            $notification->subtype,
            $notification->transaction_info,
            $notification->renewal_info,
            $notification->payload,
            $notification,
        ), $exception);
    }

    public function test_the_url_command_prints_a_url_that_passes_the_signature_check(): void
    {
        config(['services.app_store.verify_url_signature' => true]);

        $this->artisan('app-store:webhook-url')
            ->expectsOutputToContain(route('webhooks.appstore').'?signature=')
            ->assertSuccessful();

        $this->post(URL::signedRoute('webhooks.appstore', absolute: false))->assertStatus(400);
        $this->post(route('webhooks.appstore'))->assertForbidden();
    }
}
