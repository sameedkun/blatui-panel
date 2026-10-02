<?php

namespace Tests\Feature\Services\Subscription;

use App\Enum\PaymentProvider;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Services\Subscription\ProviderSubscriptionService;
use App\Services\Subscription\SubscriptionService;
use App\Support\Money\Money;
use App\Support\Subscription\ProviderTransaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The ledger's last line of defence against double-counting money is the
 * database, not the application's "already recorded?" check — that check can
 * be beaten by two queue workers racing, a lapsed lock, or a future provider
 * integration that doesn't lock at all.
 */
class SubscriptionLedgerIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_rejects_a_second_row_for_the_same_provider_event(): void
    {
        $subscription = Subscription::factory()->create();
        SubscriptionTransaction::factory()->for($subscription)->create(['provider' => 'appstore', 'idempotency_key' => 'charge:2000000000000001']);

        $this->expectException(UniqueConstraintViolationException::class);

        SubscriptionTransaction::factory()->for($subscription)->create(['provider' => 'appstore', 'idempotency_key' => 'charge:2000000000000001']);
    }

    public function test_the_same_key_from_two_providers_does_not_collide(): void
    {
        $subscription = Subscription::factory()->create();

        SubscriptionTransaction::factory()->for($subscription)->create(['provider' => 'appstore', 'idempotency_key' => 'charge:42']);
        SubscriptionTransaction::factory()->for($subscription)->create(['provider' => 'playstore', 'idempotency_key' => 'charge:42']);

        $this->assertSame(2, SubscriptionTransaction::count());
    }

    public function test_rows_written_without_a_provider_event_get_their_own_unique_key(): void
    {
        $subscription = Subscription::factory()->create();

        $first = SubscriptionTransaction::factory()->for($subscription)->create();
        $second = SubscriptionTransaction::factory()->for($subscription)->create();

        $this->assertStringStartsWith('manual:', $first->idempotency_key);
        $this->assertNotSame($first->idempotency_key, $second->idempotency_key);
    }

    public function test_a_charge_another_worker_recorded_first_rolls_this_attempt_back(): void
    {
        $user = User::factory()->app()->create();
        $price = PlanPrice::factory()->create();
        $winner = Subscription::factory()->for($user)->create(['provider' => PaymentProvider::AppStore, 'ends_at' => now()->addMonth()]);
        SubscriptionTransaction::factory()->for($winner)->create(['provider' => PaymentProvider::AppStore, 'idempotency_key' => 'charge:t1']);

        // The duplicate check reads stale data once — exactly what a racing worker sees
        // when the other worker's insert isn't visible yet. Only the unique index stops it.
        $service = new class(app(SubscriptionService::class)) extends ProviderSubscriptionService
        {
            public int $staleReads = 1;

            protected function findRecorded(PaymentProvider $provider, string $idempotencyKey, bool $lock = false): ?SubscriptionTransaction
            {
                return $this->staleReads-- > 0 ? null : parent::findRecorded($provider, $idempotencyKey, $lock);
            }
        };

        $result = $service->start($user, $price, new ProviderTransaction(
            provider: PaymentProvider::AppStore,
            originalTransactionId: 't1',
            transactionId: 't1',
            expiresAt: now()->addMonth(),
            amount: Money::ofMinor(999, 'USD'),
        ));

        $this->assertTrue($result->is($winner));
        $this->assertSame(1, Subscription::count(), 'the attempt\'s new subscription row was rolled back');
        $this->assertSame(1, SubscriptionTransaction::count());
        $this->assertSame('active', $winner->refresh()->status->value, 'the attempt\'s cancellation of the live row was rolled back');
        $this->assertSame(0, Activity::query()->where('properties->type', 'subscription_assigned')->count());
    }
}
