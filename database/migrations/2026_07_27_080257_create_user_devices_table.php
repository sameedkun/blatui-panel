<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_id')->nullable()
                ->constrained('personal_access_tokens')->nullOnDelete();

            $table->string('name')->nullable();
            $table->string('model')->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('os')->nullable();
            $table->string('device_type', 20)->nullable(); // enum cast in model
            $table->string('app_version')->nullable();
            $table->string('browser', 100)->nullable();

            $table->string('device_fingerprint', 64); // sha256 hex, not nullable
            $table->string('push_token')->nullable();
            $table->string('push_provider', 10)->nullable(); // fcm | apns

            $table->string('ip_address', 45)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 5)->nullable();

            $table->timestamp('blocked_at')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_fingerprint']);
            $table->index(['user_id', 'revoked_at', 'last_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
