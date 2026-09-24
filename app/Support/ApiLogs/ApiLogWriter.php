<?php

declare(strict_types=1);

namespace App\Support\ApiLogs;

use Illuminate\Support\Facades\DB;

/**
 * Bulk-inserts buffered records into api_request_logs / _payloads /
 * _exceptions in one transaction. insertOrIgnore on the request-keyed tables
 * makes a replayed batch harmless (request_id is unique there).
 *
 * @phpstan-import-type ApiLogRecord from RecordBuilder
 */
class ApiLogWriter
{
    private const int CHUNK_SIZE = 500;

    /** @var list<string> */
    private const array LOG_JSON_COLUMNS = ['timings'];

    /** @var list<string> */
    private const array PAYLOAD_JSON_COLUMNS = ['request_headers', 'query', 'request_body', 'response_headers', 'response_body', 'slow_queries'];

    /** @var list<string> */
    private const array EXCEPTION_JSON_COLUMNS = ['trace', 'previous'];

    /**
     * @param  list<ApiLogRecord>  $records
     */
    public function write(array $records): void
    {
        if ($records === []) {
            return;
        }

        $logs = [];
        $payloads = [];
        $exceptions = [];

        foreach ($records as $record) {
            $logs[] = $this->encode($record['log'], self::LOG_JSON_COLUMNS);

            if (! empty($record['payload'])) {
                $payloads[] = $this->encode($record['payload'], self::PAYLOAD_JSON_COLUMNS);
            }

            foreach ($record['exceptions'] ?? [] as $exception) {
                $exceptions[] = $this->encode($exception, self::EXCEPTION_JSON_COLUMNS);
            }
        }

        DB::transaction(function () use ($logs, $payloads, $exceptions): void {
            foreach (array_chunk($logs, self::CHUNK_SIZE) as $chunk) {
                DB::table('api_request_logs')->insertOrIgnore($chunk);
            }

            foreach (array_chunk($payloads, self::CHUNK_SIZE) as $chunk) {
                DB::table('api_request_payloads')->insertOrIgnore($chunk);
            }

            foreach (array_chunk($exceptions, self::CHUNK_SIZE) as $chunk) {
                DB::table('api_request_exceptions')->insert($chunk);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $jsonColumns
     * @return array<string, mixed>
     */
    private function encode(array $row, array $jsonColumns): array
    {
        foreach ($jsonColumns as $column) {
            if (array_key_exists($column, $row) && $row[$column] !== null) {
                $row[$column] = json_encode($row[$column], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
        }

        return $row;
    }
}
