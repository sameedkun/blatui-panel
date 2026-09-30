<?php

namespace Tests\Feature\Webhooks;

use App\Services\Webhooks\AppStore\RootCertificate;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Readdle\AppStoreServerAPI\Util\Helper;
use Tests\Concerns\SignsAppStoreNotifications;
use Tests\TestCase;

class RefreshAppStoreCertificateTest extends TestCase
{
    use SignsAppStoreNotifications;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'app-store-'.uniqid().DIRECTORY_SEPARATOR.'AppleRootCA-G3.cer';
        config(['services.app_store.root_certificate' => $this->path]);
    }

    private function fakeDownload(string $body, int $status = 200): void
    {
        Http::fake([RootCertificate::DOWNLOAD_URL => Http::response($body, $status)]);
    }

    private function appleRootDer(): string
    {
        return $this->selfSignedCertificateDer(['commonName' => 'Apple Root CA - G3', 'organizationName' => 'Apple Inc.']);
    }

    public function test_it_downloads_verifies_and_stores_the_certificate(): void
    {
        $der = $this->appleRootDer();
        $this->fakeDownload($der);

        $this->artisan('app-store:refresh-certificate')
            ->expectsOutputToContain('SHA-256')
            ->assertSuccessful();

        $this->assertSame($der, file_get_contents($this->path));
        $this->assertSame(Helper::toPEM($der), app(RootCertificate::class)->pem());
    }

    public function test_a_certificate_that_is_not_apples_root_is_rejected_and_the_old_one_kept(): void
    {
        mkdir(dirname($this->path), 0755, true);
        file_put_contents($this->path, 'existing-good-certificate');
        $this->fakeDownload($this->selfSignedCertificateDer(['commonName' => 'Evil Root CA', 'organizationName' => 'Apple Inc.']));

        $this->artisan('app-store:refresh-certificate')->assertFailed();

        $this->assertSame('existing-good-certificate', file_get_contents($this->path));
    }

    public function test_an_expired_certificate_is_rejected(): void
    {
        $this->fakeDownload($this->selfSignedCertificateDer(['commonName' => 'Apple Root CA - G3', 'organizationName' => 'Apple Inc.'], days: 0));

        $this->artisan('app-store:refresh-certificate')->assertFailed();

        $this->assertFileDoesNotExist($this->path);
    }

    public function test_a_failed_or_garbage_download_is_rejected(): void
    {
        $this->fakeDownload('', 503);
        $this->artisan('app-store:refresh-certificate')->assertFailed();

        $this->fakeDownload('<html>not a certificate</html>');
        $this->artisan('app-store:refresh-certificate')->assertFailed();

        $this->assertFileDoesNotExist($this->path);
    }

    public function test_it_is_scheduled_monthly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'app-store:refresh-certificate'));

        $this->assertNotNull($event);
        $this->assertSame('0 5 1 * *', $event->expression);
    }
}
