<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every table is keyed to the others by `request_id` (never the internal
     * numeric id), and none carries a foreign key: logs must outlive the users
     * and tokens they reference, and are written in bulk by a background flush.
     */
    public function up(): void
    {
        // Narrow, index-friendly row per request — everything the list page and
        // its filters need. Heavy JSON lives in api_request_payloads.
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->char('request_id', 30)->unique();
            $table->string('correlation_id', 64)->index();
            $table->string('method', 10);
            $table->string('path', 2048);
            $table->string('route_uri')->default('<unmatched>');
            $table->string('route_name')->nullable();
            $table->string('api_version', 20)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->unsignedTinyInteger('status_class');
            $table->decimal('duration_ms', 10, 2);
            $table->unsignedInteger('memory_peak_kb')->nullable();
            $table->unsignedSmallInteger('db_query_count')->default(0);
            $table->decimal('db_time_ms', 10, 2)->default(0);
            $table->unsignedInteger('request_size')->default(0);
            $table->unsignedInteger('response_size')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('client_type', 16)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type', 16)->nullable();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->boolean('has_payload')->default(false);
            $table->boolean('has_exception')->default(false);
            $table->unsignedSmallInteger('sample_weight')->default(1);
            $table->json('timings')->nullable();
            $table->dateTime('created_at', 3)->index();

            $table->index(['status_class', 'created_at']);
            $table->index(['route_uri', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->index(['error_code', 'created_at']);
        });

        // 0..1 per request — only written when there's something worth keeping.
        Schema::create('api_request_payloads', function (Blueprint $table) {
            $table->char('request_id', 30)->primary();
            $table->json('request_headers')->nullable();
            $table->json('query')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->json('slow_queries')->nullable();
            $table->boolean('request_truncated')->default(false);
            $table->boolean('response_truncated')->default(false);
            $table->dateTime('created_at', 3)->index();
        });

        // 0..n per request (normally one). Carries a denormalized copy of the
        // request's identity so it stays readable after the raw log is pruned.
        Schema::create('api_request_exceptions', function (Blueprint $table) {
            $table->id();
            $table->char('request_id', 30)->index();
            $table->string('correlation_id', 64)->nullable();
            $table->string('method', 10);
            $table->string('route_uri')->default('<unmatched>');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('class');
            $table->text('message');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->json('trace')->nullable();
            $table->json('previous')->nullable();
            $table->char('fingerprint', 40);
            $table->dateTime('created_at', 3)->index();

            $table->index(['fingerprint', 'created_at']);
        });

        // Hour / day / month rollups. Never read back into raw rows, so they
        // stay valid long after the raw logs they came from are pruned.
        Schema::create('api_request_stats', function (Blueprint $table) {
            $table->id();
            $table->string('period', 5);
            $table->dateTime('bucket');
            $table->string('method', 10);
            $table->string('route_uri');
            $table->string('api_version', 20)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->unsignedTinyInteger('status_class');
            $table->unsignedBigInteger('requests');
            $table->decimal('duration_sum_ms', 16, 2);
            $table->decimal('duration_min_ms', 10, 2);
            $table->decimal('duration_max_ms', 10, 2);
            $table->decimal('db_time_sum_ms', 16, 2)->default(0);
            $table->unsignedBigInteger('request_bytes')->default(0);
            $table->unsignedBigInteger('response_bytes')->default(0);

            // Latency histogram: requests whose duration falls in (previous bound, this bound] ms.
            foreach ([10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000] as $bound) {
                $table->unsignedBigInteger("h_le_{$bound}")->default(0);
            }
            $table->unsignedBigInteger('h_inf')->default(0);

            $table->unique(['period', 'bucket', 'method', 'route_uri', 'status_code'], 'api_request_stats_unique');
            $table->index(['period', 'status_class', 'bucket']);
            $table->index(['period', 'route_uri', 'status_class', 'bucket']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_stats');
        Schema::dropIfExists('api_request_exceptions');
        Schema::dropIfExists('api_request_payloads');
        Schema::dropIfExists('api_request_logs');
    }
};
