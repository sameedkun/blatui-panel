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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_price_id')->constrained()->restrictOnDelete();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();

            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            $table->string('status', 20)->default('trialing');

            $table->string('cancelled_by', 10)->nullable();
            $table->string('cancelled_reason')->nullable();

            $table->boolean('is_recurring')->default(true);

            $table->string('provider', 30)->default('local');

            $table->foreignId('previous_subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();
            $table->json('proration_meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'ends_at']);
            $table->index(['provider', 'status']);
        });

        Schema::create('subscription_receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider', 30);

            $table->string('type', 20)->default('initial');

            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_original_id')->nullable();
            $table->json('payload')->nullable();

            $table->string('notification_provider', 30)->nullable();
            $table->unsignedBigInteger('notification_id')->nullable();

            $table->timestamps();

            $table->index(['provider', 'provider_transaction_id']);
            $table->index(['provider', 'provider_original_id']);
            $table->index(['notification_provider', 'notification_id'], 'subscription_receipts_notification_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_receipts');
        Schema::dropIfExists('subscriptions');
    }
};
