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
        Schema::create('apple_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notification_type');
            $table->string('subtype')->nullable();
            $table->string('notification_uuid')->unique();
            $table->string('version');
            $table->timestamp('signed_date');
            $table->json('payload'); // The full notification payload
            $table->json('transaction_info')->nullable(); // Decoded transaction info
            $table->json('renewal_info')->nullable(); // Decoded renewal info
            $table->string('app_account_token')->nullable(); // The app account token from the notification
            $table->string('original_transaction_id');
            $table->string('transaction_id')->nullable();
            $table->string('product_id')->nullable();
            $table->boolean('processed')->default(false); // Whether this notification has been processed
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('app_account_token');
            $table->index('original_transaction_id');
            $table->index('notification_type');
            $table->index('processed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apple_notifications');
    }
};
