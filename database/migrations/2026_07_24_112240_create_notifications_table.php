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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Content
            $table->string('title');
            $table->text('message');
            $table->string('type', 50)->default('general');
            $table->string('link')->nullable();

            // Push notification
            $table->string('push_status', 30)->default('pending');
            $table->timestamp('push_sent_at')->nullable();
            $table->text('push_error')->nullable();
            $table->string('onesignal_notification_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
