<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;
use OpenSSLAsymmetricKey;
use Readdle\AppStoreServerAPI\Util\ASN1SequenceOfInteger;
use Readdle\AppStoreServerAPI\Util\Helper;

/**
 * Builds App Store Server Notification V2 bodies signed exactly the way Apple
 * signs them (ES256 JWS with a three-certificate `x5c` chain), but rooted in a
 * throwaway CA generated per test run — so decoding and signature verification
 * run for real instead of being mocked.
 */
trait SignsAppStoreNotifications
{
    /** @var array{root: string, x5c: list<string>, key: OpenSSLAsymmetricKey}|null */
    private static ?array $appStoreChain = null;

    /** Point the decoder at the test CA's root certificate. */
    protected function trustTestAppStoreRoot(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'apple-root');
        file_put_contents($path, $this->appStoreChain()['root']);

        config(['services.app_store.root_certificate' => $path]);
    }

    /** Point the decoder at a root CA that did NOT issue the signing chain. */
    protected function trustUnrelatedAppStoreRoot(): void
    {
        $options = $this->openSslOptions();
        $key = openssl_pkey_new($options);
        $certificate = openssl_csr_sign(openssl_csr_new(['commonName' => 'Unrelated Root'], $key, $options), null, $key, 1, $options + ['x509_extensions' => 'ca']);
        openssl_x509_export($certificate, $pem);

        $path = tempnam(sys_get_temp_dir(), 'apple-root');
        file_put_contents($path, $pem);

        config(['services.app_store.root_certificate' => $path]);
    }

    /**
     * @param  array<string, mixed>|null  $transaction  Decoded signedTransactionInfo claims.
     * @param  array<string, mixed>|null  $renewal  Decoded signedRenewalInfo claims.
     * @param  array<string, mixed>  $overrides  Top-level / `data` overrides.
     */
    protected function appStoreNotificationBody(
        string $type,
        ?string $subtype = null,
        ?array $transaction = null,
        ?array $renewal = null,
        array $overrides = [],
    ): string {
        $data = array_filter([
            'environment' => 'Production',
            'bundleId' => 'com.example.app',
            'appAppleId' => 1234567890,
            'bundleVersion' => '1.0',
            'signedTransactionInfo' => $transaction ? $this->signAppStoreJws($transaction) : null,
            'signedRenewalInfo' => $renewal ? $this->signAppStoreJws($renewal) : null,
            ...($overrides['data'] ?? []),
        ], fn (mixed $value): bool => $value !== null);

        unset($overrides['data']);

        return json_encode(['signedPayload' => $this->signAppStoreJws([
            'notificationType' => $type,
            'subtype' => $subtype,
            'notificationUUID' => (string) Str::uuid(),
            'version' => '2.0',
            'signedDate' => now()->getTimestampMs(),
            'data' => $data,
            ...$overrides,
        ])]);
    }

    /**
     * A realistic decoded JWSTransaction for an auto-renewable subscription.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function appStoreTransaction(array $overrides = []): array
    {
        return [
            'transactionId' => '2000000000000001',
            'originalTransactionId' => '2000000000000001',
            'webOrderLineItemId' => '2000000000000100',
            'bundleId' => 'com.example.app',
            'productId' => 'com.example.app.pro.monthly',
            'subscriptionGroupIdentifier' => '21000000',
            'purchaseDate' => now()->getTimestampMs(),
            'originalPurchaseDate' => now()->getTimestampMs(),
            'expiresDate' => now()->addMonth()->getTimestampMs(),
            'quantity' => 1,
            'type' => 'Auto-Renewable Subscription',
            'inAppOwnershipType' => 'PURCHASED',
            'signedDate' => now()->getTimestampMs(),
            'environment' => 'Production',
            'transactionReason' => 'PURCHASE',
            'storefront' => 'USA',
            'storefrontId' => '143441',
            'price' => 9990,
            'currency' => 'USD',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function appStoreRenewal(array $overrides = []): array
    {
        return [
            'originalTransactionId' => '2000000000000001',
            'autoRenewProductId' => 'com.example.app.pro.monthly',
            'productId' => 'com.example.app.pro.monthly',
            'autoRenewStatus' => 1,
            'signedDate' => now()->getTimestampMs(),
            'environment' => 'Production',
            'recentSubscriptionStartDate' => now()->getTimestampMs(),
            'renewalDate' => now()->addMonth()->getTimestampMs(),
            ...$overrides,
        ];
    }

    /** @param  array<string, mixed>  $claims */
    protected function signAppStoreJws(array $claims): string
    {
        $chain = $this->appStoreChain();

        $signingInput = Helper::base64Encode(json_encode(['alg' => 'ES256', 'x5c' => $chain['x5c']]))
            .'.'.Helper::base64Encode(json_encode($claims));

        openssl_sign($signingInput, $derSignature, $chain['key'], OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.Helper::base64Encode(hex2bin(ASN1SequenceOfInteger::toHex($derSignature)));
    }

    /** @return array{root: string, x5c: list<string>, key: OpenSSLAsymmetricKey} */
    private function appStoreChain(): array
    {
        if (self::$appStoreChain !== null) {
            return self::$appStoreChain;
        }

        $options = $this->openSslOptions();

        $issue = function (string $name, $issuer, $issuerKey, bool $isCa) use ($options): array {
            $key = openssl_pkey_new($options);
            $csr = openssl_csr_new(['commonName' => $name], $key, $options);
            $certificate = openssl_csr_sign($csr, $issuer, $issuerKey ?? $key, 1, $options + ($isCa ? ['x509_extensions' => 'ca'] : []));
            openssl_x509_export($certificate, $pem);

            return [$certificate, $key, $pem];
        };

        [$root, $rootKey, $rootPem] = $issue('Test Root CA', null, null, true);
        [$intermediate, $intermediateKey, $intermediatePem] = $issue('Test Intermediate CA', $root, $rootKey, true);
        [, $leafKey, $leafPem] = $issue('Test Leaf', $intermediate, $intermediateKey, false);

        $der = fn (string $pem): string => preg_replace('/-----[^-]+-----|\s+/', '', $pem);

        return self::$appStoreChain = [
            'root' => $rootPem,
            'x5c' => [$der($leafPem), $der($intermediatePem), $der($rootPem)],
            'key' => $leafKey,
        ];
    }

    /**
     * A self-signed CA certificate (DER bytes, as apple.com serves it) with the given subject.
     *
     * @param  array<string, string>  $subject  e.g. ['commonName' => 'Apple Root CA - G3', 'organizationName' => 'Apple Inc.']
     */
    protected function selfSignedCertificateDer(array $subject, int $days = 365): string
    {
        $options = $this->openSslOptions();
        $key = openssl_pkey_new($options);
        $certificate = openssl_csr_sign(openssl_csr_new($subject, $key, $options), null, $key, $days, $options + ['x509_extensions' => 'ca']);
        openssl_x509_export($certificate, $pem);

        return base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem));
    }

    /** @return array<string, mixed> */
    private function openSslOptions(): array
    {
        // Windows PHP builds ship without a default openssl.cnf; a minimal one is enough.
        $config = tempnam(sys_get_temp_dir(), 'openssl');
        file_put_contents($config, "[req]\ndistinguished_name = dn\n[dn]\n[ca]\nbasicConstraints = critical,CA:true\n");

        return ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'digest_alg' => 'sha256', 'config' => $config];
    }
}
