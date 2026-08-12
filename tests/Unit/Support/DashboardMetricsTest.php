<?php

namespace Tests\Unit\Support;

use App\Enum\SubscriptionStatus;
use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketStatus;
use App\Enum\UserType;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Dashboard\AudienceMetrics;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\RevenueMetrics;
use App\Support\Dashboard\SecurityMetrics;
use App\Support\Dashboard\SupportMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    // ── DateRange ─────────────────────────────────────────────────────────────

    public function test_previous_window_is_the_same_length_and_ends_where_the_current_one_starts(): void
    {
        $range = DateRange::Month;

        $this->assertSame(30, $range->days());
        $this->assertTrue($range->previousEnd()->equalTo($range->start()));
        $this->assertSame(30, (int) $range->previousStart()->diffInDays($range->previousEnd()));
    }

    public function test_a_year_range_buckets_by_month_rather_than_by_day(): void
    {
        $this->assertSame('month', DateRange::Year->grouping());
        $this->assertSame(12, DateRange::Year->buckets());

        $this->assertSame('day', DateRange::Quarter->grouping());
        $this->assertSame(90, DateRange::Quarter->buckets());
    }

    public function test_an_unknown_range_value_falls_back_to_the_default_instead_of_throwing(): void
    {
        $this->assertSame(DateRange::Month, DateRange::fromValue('nonsense'));
        $this->assertSame(DateRange::Month, DateRange::fromValue(null));
        $this->assertSame(DateRange::Week, DateRange::fromValue('7d'));
    }

    // ── Audience ──────────────────────────────────────────────────────────────

    public function test_user_counts_are_partitioned_by_type_and_exclude_trashed_accounts(): void
    {
        User::factory()->count(3)->create(['type' => UserType::App]);
        User::factory()->count(2)->create(['type' => UserType::Guest]);
        User::factory()->create(['type' => UserType::Staff]);
        User::factory()->create(['type' => UserType::App])->delete();

        $metrics = new AudienceMetrics;

        $this->assertSame(3, $metrics->totalUsers());
        $this->assertSame(2, $metrics->totalGuests());
        $this->assertSame(1, $metrics->totalStaff());
    }

    public function test_new_user_trend_compares_the_window_against_the_one_before_it(): void
    {
        // 4 in the last 30 days, 2 in the 30 days before that → +100%.
        User::factory()->count(4)->create([
            'type' => UserType::App,
            'created_at' => Date::now()->subDays(5),
        ]);
        User::factory()->count(2)->create([
            'type' => UserType::App,
            'created_at' => Date::now()->subDays(40),
        ]);

        $result = (new AudienceMetrics)->newUsers(DateRange::Month);

        $this->assertSame(4, $result['value']);
        $this->assertSame(2, $result['previous']);
        $this->assertSame(100.0, $result['change']);
    }

    public function test_growth_from_an_empty_previous_window_reports_no_percentage(): void
    {
        User::factory()->count(3)->create([
            'type' => UserType::App,
            'created_at' => Date::now()->subDays(2),
        ]);

        $result = (new AudienceMetrics)->newUsers(DateRange::Month);

        $this->assertSame(3, $result['value']);
        $this->assertNull($result['change'], 'Growth from zero has no meaningful percentage.');
    }

    public function test_signup_series_covers_every_day_in_the_range_including_empty_ones(): void
    {
        User::factory()->create([
            'type' => UserType::App,
            'created_at' => Date::now()->subDays(3),
        ]);

        $series = (new AudienceMetrics)->signupSeries(DateRange::Week);

        $this->assertCount(7, $series['users'], 'A 7-day range must always produce 7 buckets.');
        $this->assertCount(7, $series['labels']);
        $this->assertCount(7, $series['guests']);
        $this->assertSame(1, array_sum($series['users']));
        $this->assertSame(0, array_sum($series['guests']));
    }

    public function test_verified_rate_is_a_percentage_of_app_users_only(): void
    {
        User::factory()->count(3)->create(['type' => UserType::App, 'email_verified_at' => now()]);
        User::factory()->create(['type' => UserType::App, 'email_verified_at' => null]);
        User::factory()->create(['type' => UserType::Staff, 'email_verified_at' => null]);

        $this->assertSame(75.0, (new AudienceMetrics)->verifiedRate());
    }

    public function test_verified_rate_is_zero_rather_than_a_division_error_with_no_users(): void
    {
        $this->assertSame(0.0, (new AudienceMetrics)->verifiedRate());
    }

    // ── Revenue ───────────────────────────────────────────────────────────────

    public function test_active_subscription_count_covers_trialing_active_and_grace(): void
    {
        $this->subscriptionWith(SubscriptionStatus::Trialing);
        $this->subscriptionWith(SubscriptionStatus::Active);
        $this->subscriptionWith(SubscriptionStatus::Grace);
        $this->subscriptionWith(SubscriptionStatus::Expired);
        $this->subscriptionWith(SubscriptionStatus::Cancelled);

        $this->assertSame(3, (new RevenueMetrics)->activeSubscriptions());
    }

    public function test_revenue_sums_only_subscriptions_starting_inside_the_window(): void
    {
        $this->subscriptionWith(SubscriptionStatus::Active, [
            'amount_paid' => 100,
            'starts_at' => Date::now()->subDays(3),
        ]);
        $this->subscriptionWith(SubscriptionStatus::Active, [
            'amount_paid' => 50,
            'starts_at' => Date::now()->subDays(200),
        ]);

        $result = (new RevenueMetrics)->revenue(DateRange::Month);

        $this->assertSame(100.0, $result['value']);
    }

    public function test_average_revenue_per_user_counts_a_repeat_subscriber_once(): void
    {
        $user = User::factory()->create(['type' => UserType::App]);

        $this->subscriptionWith(SubscriptionStatus::Expired, ['amount_paid' => 60, 'user_id' => $user->id]);
        $this->subscriptionWith(SubscriptionStatus::Active, ['amount_paid' => 40, 'user_id' => $user->id]);

        // One customer, $100 total — not two customers at $50.
        $this->assertSame(100.0, (new RevenueMetrics)->averageRevenuePerUser());
    }

    public function test_trial_conversion_ignores_subscriptions_still_mid_trial(): void
    {
        $this->subscriptionWith(SubscriptionStatus::Active, ['trial_ends_at' => Date::now()->subDay()]);
        $this->subscriptionWith(SubscriptionStatus::Expired, ['trial_ends_at' => Date::now()->subDay()]);
        // Never had a trial at all — outside the denominator entirely.
        $this->subscriptionWith(SubscriptionStatus::Active, ['trial_ends_at' => null]);

        $this->assertSame(50.0, (new RevenueMetrics)->trialConversionRate());
    }

    public function test_trial_conversion_is_zero_when_nothing_has_ever_trialed(): void
    {
        $this->assertSame(0.0, (new RevenueMetrics)->trialConversionRate());
    }

    // ── Support ───────────────────────────────────────────────────────────────

    public function test_open_ticket_count_excludes_resolved_and_closed(): void
    {
        Ticket::factory()->create(['status' => TicketStatus::Open]);
        Ticket::factory()->create(['status' => TicketStatus::Pending]);
        Ticket::factory()->create(['status' => TicketStatus::Resolved]);
        Ticket::factory()->create(['status' => TicketStatus::Closed]);

        $this->assertSame(2, app(SupportMetrics::class)->openTickets());
    }

    public function test_median_first_response_measures_the_first_staff_reply_not_the_latest(): void
    {
        $ticket = Ticket::factory()->create(['created_at' => Date::now()->subDays(2)]);

        // First staff reply 2h in; a later one must not move the figure.
        TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'author_type' => TicketMessageAuthorType::Staff,
            'created_at' => $ticket->created_at->addHours(2),
        ]);
        TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'author_type' => TicketMessageAuthorType::Staff,
            'created_at' => $ticket->created_at->addHours(20),
        ]);

        $this->assertSame(2.0, app(SupportMetrics::class)->medianFirstResponseHours(DateRange::Month));
    }

    public function test_median_first_response_is_null_when_no_ticket_has_a_staff_reply(): void
    {
        Ticket::factory()->create(['created_at' => Date::now()->subDay()]);

        $this->assertNull(app(SupportMetrics::class)->medianFirstResponseHours(DateRange::Month));
    }

    // ── Security ──────────────────────────────────────────────────────────────

    public function test_device_status_counts_follow_the_models_own_scopes(): void
    {
        UserDevice::factory()->count(2)->create(['revoked_at' => null, 'blocked_at' => null]);
        UserDevice::factory()->create(['revoked_at' => now(), 'blocked_at' => null]);
        UserDevice::factory()->create(['blocked_at' => now(), 'revoked_at' => null]);

        $metrics = new SecurityMetrics;

        $this->assertSame(2, $metrics->activeDevices());
        $this->assertSame(1, $metrics->revokedDevices());
        $this->assertSame(1, $metrics->blockedDevices());
    }

    public function test_shared_fingerprints_only_counts_a_fingerprint_seen_on_multiple_accounts(): void
    {
        $shared = hash('sha256', 'shared-device');
        $solo = hash('sha256', 'solo-device');

        UserDevice::factory()->create(['device_fingerprint' => $shared, 'user_id' => User::factory()]);
        UserDevice::factory()->create(['device_fingerprint' => $shared, 'user_id' => User::factory()]);
        UserDevice::factory()->create(['device_fingerprint' => $solo, 'user_id' => User::factory()]);

        $this->assertSame(1, (new SecurityMetrics)->sharedFingerprints());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function subscriptionWith(SubscriptionStatus $status, array $attributes = []): Subscription
    {
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->create(['plan_id' => $plan->id]);

        return Subscription::factory()->create([
            'plan_id' => $plan->id,
            'plan_price_id' => $price->id,
            'status' => $status,
            ...$attributes,
        ]);
    }
}
