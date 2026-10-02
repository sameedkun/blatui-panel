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
        // The entitlement: what access a user has, for which period, and why.
        // No money here — a subscription can be paid in several currencies, or
        // not at all (a grant). Payments live in subscription_transactions.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            // Nullable and detached (not cascaded) on user deletion: the financial
            // history this row carries must outlive the account. NULL = ownerless.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_price_id')->constrained()->restrictOnDelete();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();

            $table->string('status', 20)->default('trialing');

            $table->string('cancelled_by', 10)->nullable();
            $table->string('cancelled_reason')->nullable();

            $table->boolean('is_recurring')->default(true);

            // Billing integration (local, appstore, playstore, stripe, ...).
            $table->string('provider', 30)->default('local');

            // Why it exists (purchase, admin, promotional, migration, system).
            $table->string('source', 20)->default('purchase');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('grant_reason')->nullable();

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

        // The financial ledger: one row per real money movement (charge,
        // renewal, plan-change charge, refund, refund reversal).
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider', 30);

            $table->string('type', 20)->default('initial');

            // Gross amount in the currency's ISO 4217 minor unit (always >= 0;
            // the type gives the direction), normalised from the provider's unit.
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('purchased_at')->nullable();
            // Billing periods a charge pays for — 1 normally, N for a pay-up-front
            // offer — so recurring value (MRR) can spread it over what it covers.
            $table->unsignedSmallInteger('periods_covered')->default(1);

            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_original_id')->nullable();

            // One key per real money movement (e.g. `charge:{transactionId}`,
            // `refund:{transactionId}:{event}`, `reversal:{refund key}`). The unique
            // index below is what makes a replayed or concurrently processed
            // provider event impossible to record twice.
            $table->string('idempotency_key', 191);

            // A refund points at the charge it refunds; a reversal at the refund it reverses.
            $table->foreignId('related_transaction_id')
                ->nullable()
                ->constrained('subscription_transactions')
                ->nullOnDelete();

            $table->json('payload')->nullable();

            // Internal row id in that provider's raw notification table
            // (e.g. apple_notifications.id) — not the provider's notification UUID.
            $table->string('notification_provider', 30)->nullable();
            $table->unsignedBigInteger('notification_id')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'idempotency_key']);
            $table->index(['provider', 'provider_transaction_id']);
            $table->index(['provider', 'provider_original_id']);
            $table->index(['notification_provider', 'notification_id'], 'subscription_transactions_notification_idx');
            $table->index(['type', 'purchased_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
        Schema::dropIfExists('subscriptions');
    }
};
