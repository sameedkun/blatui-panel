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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_best_deal')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('compare_at_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedSmallInteger('billing_period')->default(1);
            $table->string('billing_interval', 10)->default('month');
            $table->unsignedSmallInteger('trial_period')->default(0);
            $table->string('trial_interval', 10)->default('day');
            $table->unsignedSmallInteger('grace_period')->default(0);
            $table->string('grace_interval', 10)->default('day');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plan_price_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_price_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('external_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_price_id', 'provider']);
            $table->index(['provider', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_price_providers');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
