<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

/**
 * Strips secrets and personal data out of everything an API log stores —
 * headers, query strings, request/response bodies, exception messages —
 * before it ever leaves the request process. Rules come from
 * config('api_logs.sanitizer'):
 *
 *   - Secrets are replaced wholesale with [REDACTED]: headers by name, body
 *     keys by (normalized) substring or exact match, and — regardless of key —
 *     any value shaped like a JWT, a bearer credential, a Sanctum token, or a
 *     Luhn-valid card number.
 *   - Personal data is partially masked so a log stays useful for debugging
 *
 *     without holding the real value: j***@gmail.com, +92******4567,
 *     S***** C***, 1990-**-**. Any value that is itself an email address is
 *     masked too, whatever its key.
 */
class Sanitizer
{
    public const string REDACTED = '[REDACTED]';

    /** @var array{redact_headers: list<string>, redact_keys_containing: list<string>, redact_keys_exact: list<string>, mask_keys: array<string, string>} */
    private array $rules;

    /**
     * @param  array<string, mixed>|null  $rules  Defaults to config('api_logs.sanitizer').
     */
    public function __construct(?array $rules = null)
    {
        $rules ??= (array) config('api_logs.sanitizer', []);

        $this->rules = [
            'redact_headers' => array_map('strtolower', $rules['redact_headers'] ?? []),
            'redact_keys_containing' => array_map([$this, 'normalizeKey'], $rules['redact_keys_containing'] ?? []),
            'redact_keys_exact' => array_map([$this, 'normalizeKey'], $rules['redact_keys_exact'] ?? []),
            'mask_keys' => collect($rules['mask_keys'] ?? [])
                ->mapWithKeys(fn (string $strategy, string $key): array => [$this->normalizeKey($key) => $strategy])
                ->all(),
        ];
    }

    /**
     * Sanitize a header bag (Symfony's `name => list<value>` shape, or plain
     * `name => value`). Single-value lists are flattened for readability.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, string|list<string>>
     */
    public function headers(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $values) {
            $name = strtolower((string) $name);
            $values = array_map('strval', is_array($values) ? array_values($values) : [$values]);

            $values = array_map(fn (string $value): string => match (true) {
                in_array($name, ['authorization', 'proxy-authorization'], true) => $this->redactAuthorization($value),
                in_array($name, $this->rules['redact_headers'], true) => self::REDACTED,
                default => $this->scrubString($value),
            }, $values);

            $sanitized[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return $sanitized;
    }

    /**
     * Recursively sanitize arbitrary decoded data (request input, a JSON
     * response body, route parameters).
     */
    public function data(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];

            foreach ($data as $key => $value) {
                $sanitized[$key] = is_string($key) ? $this->value($key, $value) : $this->data($value);
            }

            return $sanitized;
        }

        return is_string($data) ? $this->scrubString($data) : $data;
    }

    /**
     * Free text with no key to go on (an exception message) — pattern-based
     * rules only, applied to every token-like and email-like run inside it.
     */
    public function text(string $text): string
    {
        $text = preg_replace('/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*/', self::REDACTED, $text) ?? $text;
        $text = preg_replace('/\b(Bearer)\s+\S+/i', '$1 '.self::REDACTED, $text) ?? $text;
        $text = preg_replace('/\b\d+\|[A-Za-z0-9]{40,}\b/', self::REDACTED, $text) ?? $text;

        return preg_replace_callback(
            '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
            fn (array $match): string => $this->maskEmail($match[0]),
            $text,
        ) ?? $text;
    }

    private function value(string $key, mixed $value): mixed
    {
        $normalized = $this->normalizeKey($key);

        if ($this->isSecretNormalizedKey($normalized)) {
            return $value === null ? null : self::REDACTED;
        }

        if (isset($this->rules['mask_keys'][$normalized]) && (is_string($value) || is_int($value))) {
            return $this->mask((string) $value, $this->rules['mask_keys'][$normalized]);
        }

        return $this->data($value);
    }

    /** Whether a value stored under $key is always fully redacted. */
    public function isSecretKey(string $key): bool
    {
        return $this->isSecretNormalizedKey($this->normalizeKey($key));
    }

    private function isSecretNormalizedKey(string $normalizedKey): bool
    {
        if (in_array($normalizedKey, $this->rules['redact_keys_exact'], true)) {
            return true;
        }

        foreach ($this->rules['redact_keys_containing'] as $needle) {
            if ($needle !== '' && str_contains($normalizedKey, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Value-shape rules that apply whatever key a string sits under. */
    private function scrubString(string $value): string
    {
        $trimmed = trim($value);

        return match (true) {
            preg_match('/^eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*$/', $trimmed) === 1 => self::REDACTED,
            preg_match('/^Bearer\s+\S+$/i', $trimmed) === 1 => $this->redactAuthorization($trimmed),
            preg_match('/^\d+\|[A-Za-z0-9]{40,}$/', $trimmed) === 1 => self::REDACTED,
            $this->looksLikeCardNumber($trimmed) => self::REDACTED,
            filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false => $this->maskEmail($trimmed),
            default => $value,
        };
    }

    /**
     * Keeps the auth scheme and a Sanctum token's numeric id ("Bearer 42|…")
     * — which token made the call is exactly what an investigation needs —
     * while the secret half never reaches storage.
     */
    private function redactAuthorization(string $value): string
    {
        if (preg_match('/^(\w+)\s+(\d+)\|\S+$/', trim($value), $matches) === 1) {
            return "{$matches[1]} {$matches[2]}|".self::REDACTED;
        }

        if (preg_match('/^(\w+)\s+\S+/', trim($value), $matches) === 1) {
            return "{$matches[1]} ".self::REDACTED;
        }

        return self::REDACTED;
    }

    private function mask(string $value, string $strategy): string
    {
        if ($value === '') {
            return $value;
        }

        return match ($strategy) {
            'email' => $this->maskEmail($value),
            'phone' => $this->maskPhone($value),
            'date' => $this->maskDate($value),
            default => $this->maskWords($value),
        };
    }

    private function maskEmail(string $value): string
    {
        if (! str_contains($value, '@')) {
            return $this->maskWords($value);
        }

        [$local, $domain] = explode('@', $value, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    /** Keeps a leading "+", the first two and last four digits: +92******4567. */
    private function maskPhone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        $prefix = str_starts_with(trim($value), '+') ? '+' : '';

        if (strlen($digits) <= 6) {
            return $prefix.str_repeat('*', strlen($digits));
        }

        return $prefix.substr($digits, 0, 2).str_repeat('*', strlen($digits) - 6).substr($digits, -4);
    }

    /** Keeps only the year of a Y-m-d style date. */
    private function maskDate(string $value): string
    {
        if (preg_match('/^(\d{4})[-\/.]\d{1,2}[-\/.]\d{1,2}/', $value, $matches) === 1) {
            return "{$matches[1]}-**-**";
        }

        return self::REDACTED;
    }

    /** First character of each word: "Sameed Chan" → "S***** C***". */
    private function maskWords(string $value): string
    {
        return collect(preg_split('/(\s+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [])
            ->map(fn (string $part): string => trim($part) === '' || mb_strlen($part) <= 1
                ? $part
                : mb_substr($part, 0, 1).str_repeat('*', mb_strlen($part) - 1))
            ->implode('');
    }

    private function looksLikeCardNumber(string $value): bool
    {
        if (preg_match('/^[\d -]{13,23}$/', $value) !== 1) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) < 13 || strlen($digits) > 19) {
            return false;
        }

        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    private function normalizeKey(string $key): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($key)) ?? '';
    }
}
