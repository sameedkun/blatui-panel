<?php

namespace Tests\Unit\Support\ApiLogs;

use App\Support\ApiLogs\Sanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
    private function sanitizer(): Sanitizer
    {
        $config = require __DIR__.'/../../../../config/api_logs.php';

        return new Sanitizer($config['sanitizer']);
    }

    /** @return array<string, array{0: string}> */
    public static function secretKeys(): array
    {
        return [
            'password' => ['password'],
            'password confirmation' => ['password_confirmation'],
            'current password' => ['current_password'],
            'camel-cased token' => ['accessToken'],
            'refresh token' => ['refresh_token'],
            'client secret' => ['client_secret'],
            'api key' => ['api-key'],
            'signature' => ['signature'],
            'card number' => ['card_number'],
            'cvv' => ['CVV'],
            'device fingerprint' => ['fingerprint'],
            'exact pin' => ['pin'],
            'exact otp' => ['otp'],
            'exact hash' => ['hash'],
        ];
    }

    #[DataProvider('secretKeys')]
    public function test_secret_keys_are_fully_redacted(string $key): void
    {
        $this->assertSame([$key => Sanitizer::REDACTED], $this->sanitizer()->data([$key => 'hunter2']));
    }

    public function test_short_exact_keys_do_not_substring_match(): void
    {
        $data = ['shipping' => 'express', 'hashtag' => 'laravel', 'code' => 'DEVICE_BLOCKED'];

        $this->assertSame($data, $this->sanitizer()->data($data));
    }

    public function test_redaction_applies_at_any_depth(): void
    {
        $sanitized = $this->sanitizer()->data([
            'user' => ['profile' => ['password' => 'secret', 'locale' => 'en']],
            'items' => [['token' => 'abc'], ['token' => 'def']],
        ]);

        $this->assertSame(Sanitizer::REDACTED, $sanitized['user']['profile']['password']);
        $this->assertSame('en', $sanitized['user']['profile']['locale']);
        $this->assertSame([['token' => Sanitizer::REDACTED], ['token' => Sanitizer::REDACTED]], $sanitized['items']);
    }

    public function test_a_null_secret_stays_null(): void
    {
        $this->assertSame(['password' => null], $this->sanitizer()->data(['password' => null]));
    }

    public function test_personal_data_is_partially_masked(): void
    {
        $sanitized = $this->sanitizer()->data([
            'email' => 'jane.doe@gmail.com',
            'phone' => '+92 300 1234567',
            'name' => 'Sameed Chan',
            'first_name' => 'Jane',
            'dob' => '1990-05-17',
            'address' => '12 Baker Street',
        ]);

        $this->assertSame([
            'email' => 'j***@gmail.com',
            'phone' => '+92******4567',
            'name' => 'S***** C***',
            'first_name' => 'J***',
            'dob' => '1990-**-**',
            'address' => '1* B**** S*****',
        ], $sanitized);
    }

    public function test_a_short_phone_number_is_fully_masked(): void
    {
        $this->assertSame(['phone' => '*****'], $this->sanitizer()->data(['phone' => '12345']));
    }

    public function test_an_unparseable_date_of_birth_is_redacted(): void
    {
        $this->assertSame(['dob' => Sanitizer::REDACTED], $this->sanitizer()->data(['dob' => 'May 17th']));
    }

    public function test_an_email_address_is_masked_under_any_key(): void
    {
        $this->assertSame(['contact' => 'j***@example.com'], $this->sanitizer()->data(['contact' => 'jane@example.com']));
    }

    public function test_token_shaped_values_are_redacted_under_any_key(): void
    {
        $sanitized = $this->sanitizer()->data([
            'jwt' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl',
            'sanctum' => '42|'.str_repeat('a', 40),
            'header_copy' => 'Bearer abc.def',
            'plain' => 'hello world',
        ]);

        $this->assertSame(Sanitizer::REDACTED, $sanitized['jwt']);
        $this->assertSame(Sanitizer::REDACTED, $sanitized['sanctum']);
        $this->assertSame('Bearer '.Sanitizer::REDACTED, $sanitized['header_copy']);
        $this->assertSame('hello world', $sanitized['plain']);
    }

    public function test_luhn_valid_card_numbers_are_redacted_but_other_numbers_are_not(): void
    {
        $sanitized = $this->sanitizer()->data([
            'reference' => '4111 1111 1111 1111',
            'order_number' => '1234567890123',
        ]);

        $this->assertSame(Sanitizer::REDACTED, $sanitized['reference']);
        $this->assertSame('1234567890123', $sanitized['order_number']);
    }

    public function test_the_authorization_header_keeps_only_the_scheme_and_sanctum_token_id(): void
    {
        $headers = $this->sanitizer()->headers([
            'Authorization' => ['Bearer 42|'.str_repeat('x', 40)],
            'Proxy-Authorization' => ['Basic dXNlcjpwYXNz'],
        ]);

        $this->assertSame('Bearer 42|'.Sanitizer::REDACTED, $headers['authorization']);
        $this->assertSame('Basic '.Sanitizer::REDACTED, $headers['proxy-authorization']);
    }

    public function test_sensitive_headers_are_redacted_and_others_flattened(): void
    {
        $headers = $this->sanitizer()->headers([
            'Cookie' => ['session=abc'],
            'X-Api-Key' => ['live_123'],
            'Accept' => ['application/json'],
            'X-Multi' => ['one', 'two'],
        ]);

        $this->assertSame([
            'cookie' => Sanitizer::REDACTED,
            'x-api-key' => Sanitizer::REDACTED,
            'accept' => 'application/json',
            'x-multi' => ['one', 'two'],
        ], $headers);
    }

    public function test_free_text_is_scrubbed_of_tokens_and_emails(): void
    {
        $text = $this->sanitizer()->text('User jane@example.com sent Bearer abc123 and 7|'.str_repeat('z', 40));

        $this->assertSame('User j***@example.com sent Bearer '.Sanitizer::REDACTED.' and '.Sanitizer::REDACTED, $text);
    }

    public function test_it_reports_whether_a_key_is_secret(): void
    {
        $this->assertTrue($this->sanitizer()->isSecretKey('hash'));
        $this->assertTrue($this->sanitizer()->isSecretKey('reset_token'));
        $this->assertFalse($this->sanitizer()->isSecretKey('ticket'));
    }
}
