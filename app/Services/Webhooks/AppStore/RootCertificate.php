<?php

namespace App\Services\Webhooks\AppStore;

use Illuminate\Support\Facades\Http;
use Readdle\AppStoreServerAPI\Util\Helper;
use RuntimeException;

/**
 * The Apple Root CA - G3 certificate every App Store notification chain must
 * lead back to. Owns where it lives (`services.app_store.root_certificate`),
 * loading it for {@see NotificationDecoder}, and refreshing it from Apple
 * (`php artisan app-store:refresh-certificate`, also scheduled monthly).
 */
class RootCertificate
{
    public const DOWNLOAD_URL = 'https://www.apple.com/certificateauthority/AppleRootCA-G3.cer';

    private const EXPECTED_SUBJECT = ['CN' => 'Apple Root CA - G3', 'O' => 'Apple Inc.'];

    /** Absolute path of the configured certificate file (relative config values are from the project root). */
    public function path(): string
    {
        $path = (string) config('services.app_store.root_certificate');

        return preg_match('#^([a-zA-Z]:)?[\\\\/]#', $path) ? $path : base_path($path);
    }

    /**
     * The certificate in the form `readdle/app-store-server-api` expects —
     * the PEM text as-is, or a DER file's base64 body.
     *
     * @throws RuntimeException when the file is missing, so verification can never silently skip the root check.
     */
    public function pem(): string
    {
        $contents = is_file($this->path()) ? file_get_contents($this->path()) : false;

        if (! $contents) {
            throw new RuntimeException("Apple root certificate not found at [{$this->path()}] — run `php artisan app-store:refresh-certificate`.");
        }

        return str_contains($contents, '-----BEGIN CERTIFICATE-----')
            ? str_replace("\r\n", "\n", $contents)
            : Helper::toPEM($contents);
    }

    /**
     * Downloads the certificate from Apple, checks it really is a valid,
     * self-signed Apple Root CA - G3, and only then replaces the stored file
     * (atomically) — a failed or tampered download never clobbers a good one.
     *
     * @return string The new certificate's SHA-256 fingerprint.
     *
     * @throws RuntimeException when the download fails or isn't the expected certificate.
     */
    public function refresh(): string
    {
        $response = Http::timeout(30)->retry(2, 1000, throw: false)->get(self::DOWNLOAD_URL);

        if (! $response->successful() || $response->body() === '') {
            throw new RuntimeException("Downloading the Apple root certificate failed (HTTP {$response->status()}).");
        }

        $der = $response->body();
        $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
        $this->assertIsAppleRoot($pem);

        $path = $this->path();

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0755, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('Could not create directory ['.dirname($path).'].');
        }

        if (file_put_contents("{$path}.tmp", $der) === false || ! rename("{$path}.tmp", $path)) {
            throw new RuntimeException("Could not write the Apple root certificate to [{$path}].");
        }

        return (string) openssl_x509_fingerprint($pem, 'sha256');
    }

    private function assertIsAppleRoot(string $pem): void
    {
        $certificate = @openssl_x509_parse($pem);

        if (! is_array($certificate)) {
            throw new RuntimeException('The downloaded file is not an X.509 certificate.');
        }

        foreach (self::EXPECTED_SUBJECT as $field => $expected) {
            if (($certificate['subject'][$field] ?? null) !== $expected) {
                throw new RuntimeException("Unexpected certificate subject {$field}: [".($certificate['subject'][$field] ?? '').'].');
            }
        }

        if (($certificate['validTo_time_t'] ?? 0) <= time()) {
            throw new RuntimeException('The downloaded certificate has expired.');
        }

        if (openssl_x509_verify($pem, $pem) !== 1) {
            throw new RuntimeException('The downloaded certificate is not self-signed.');
        }
    }
}
