<?php

namespace Tests\Feature\Services\Dashboard;

use App\Enum\ActivityAction;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketStatus;
use App\Models\BlockedIp;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dashboard\Analytics\RevenueSection;
use App\Support\Dashboard\Blocks\KeyFigures;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\Format;
use App\Support\Dashboard\Metrics\AudienceMetrics;
use App\Support\Dashboard\Metrics\RevenueMetrics;
use App\Support\Dashboard\Metrics\SecurityMetrics;
use App\Support\Dashboard\Metrics\SubscriptionMetrics;
use App\Support\Dashboard\Metrics\SupportMetrics;
use App\Support\Dashboard\Overview\Kpis\MonthlyRecurringRevenue;
use App\Support\Dashboard\Overview\Kpis\Revenue as RevenueKpi;
use App\Support\Dashboard\TimeSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    private DateRange $range;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Date::parse('2026-09-28 12:00:00'));
        $this->range = DateRange::preset('30d'); // 2026-08-30 → now
    }

    public function test_time_series_fill_every_bucket_and_only_count_the_range(): void
    {
        $this->appUser(['created_at' => '2026-09-01 10:00']);
        $this->appUser(['created_at' => '2026-09-01 18:00']);
        $this->appUser(['created_at' => '2026-09-20 09:00']);
        $this->appUser(['created_at' => '2026-07-01 09:00']); // before the range

        $series = TimeSeries::count(User::query()->appUsers(), $this->range);

        $this->assertCount(30, $series);
        $this->assertSame(2, $series['2026-09-01']);
        $this->assertSame(1, $series['2026-09-20']);
        $this->assertSame(0, $series['2026-09-02']);
        $this->assertSame(3, array_sum($series));
    }

    public function test_audience_growth_activity_and_breakdown(): void
    {
        $audience = new AudienceMetrics;

        $veteran = $this->appUser(['created_at' => '2026-06-01']);
        $newcomer = $this->appUser(['created_at' => '2026-09-10', 'email_verified_at' => '2026-09-10', 'google_id' => 'g-1']);
        $this->appUser(['created_at' => '2026-09-12', 'email_verified_at' => null]);
        $this->appUser(['created_at' => '2026-09-13', 'banned_at' => now()]);
        User::factory()->guest()->create(['created_at' => '2026-09-15', 'banned_at' => null]);
        User::factory()->create(['type' => 'staff', 'banned_at' => null]);

        $this->loginAt($veteran, '2026-09-20 08:00');
        $this->loginAt($veteran, '2026-09-21 08:00');
        $this->loginAt($newcomer, '2026-09-21 09:00');
        $this->loginAt($veteran, '2026-07-01 08:00'); // outside the range

        $this->assertSame(4, $audience->totalUsers());
        $this->assertSame(1, $audience->totalUsers($this->range->start->subSecond()));
        $this->assertSame(3, $audience->newUsers($this->range));
        $this->assertSame(2, $audience->activeUsers($this->range));
        $this->assertSame(1, $audience->returningUsers($this->range));
        $this->assertSame(1, $audience->guests());
        $this->assertSame(33.3, $audience->activationRate($this->range));
        $this->assertSame(['email' => 2, 'google' => 1, 'apple' => 0], $audience->signupMethods($this->range));

        $active = $audience->activeSeries($this->range);
        $this->assertSame(2, $active['2026-09-21']);
        $this->assertSame(1, $active['2026-09-20']);

        $growth = $audience->growthSeries($this->range);
        $this->assertSame(1, reset($growth), 'The running total starts from users that already existed.');
        $this->assertSame(4, end($growth));

        $breakdown = $audience->breakdown();
        $this->assertSame(1, $breakdown['staff']);
        $this->assertSame(1, $breakdown['banned']);
    }

    public function test_guest_conversion_rate_counts_conversions_against_convertible_guests(): void
    {
        $audience = new AudienceMetrics;
        $converted = $this->appUser(['created_at' => '2026-09-05']);

        User::factory()->guest()->count(3)->create(['created_at' => '2026-09-06']);
        ActivityLogger::log(ActivityModule::Guest, ActivityAction::Converted, $converted, causer: null);

        $this->assertSame(1, $audience->guestConversions($this->range));
        $this->assertSame(25.0, $audience->guestConversionRate($this->range));
    }

    public function test_revenue_mrr_and_breakdowns(): void
    {
        $revenue = new RevenueMetrics;
        $plan = Plan::factory()->create(['name' => 'Pro']);
        $monthly = PlanPrice::factory()->for($plan)->create(['amount' => 30, 'billing_interval' => 'month']);
        $yearly = PlanPrice::factory()->for($plan)->create(['amount' => 240, 'billing_interval' => 'year']);

        $a = $this->subscription($plan, $monthly, ['starts_at' => '2026-09-10', 'ends_at' => '2026-10-10']);
        $b = $this->subscription($plan, $yearly, ['starts_at' => '2026-09-15', 'ends_at' => '2027-09-15']);
        $refunded = $this->subscription($plan, $monthly, ['starts_at' => '2026-09-20', 'ends_at' => '2026-10-20']);
        $old = $this->subscription($plan, $monthly, ['starts_at' => '2026-07-01', 'ends_at' => '2026-08-01', 'status' => 'expired']);
        $trial = $this->subscription($plan, $monthly, ['starts_at' => '2026-09-25', 'ends_at' => '2026-10-25', 'status' => 'trialing', 'trial_ends_at' => '2026-10-02']);
        $euro = $this->subscription($plan, $monthly, ['starts_at' => '2026-09-12', 'ends_at' => '2026-10-12', 'provider' => 'appstore']);
        // A free grant: live, but never revenue, never "paying", never MRR.
        $this->subscription($plan, $monthly, ['starts_at' => '2026-09-01', 'ends_at' => '2026-12-01', 'source' => 'admin']);

        $this->charge($a, 3000, 'USD', '2026-09-10');
        $this->charge($b, 24000, 'USD', '2026-09-15');
        $this->charge($refunded, 3000, 'USD', '2026-09-20');
        $this->charge($refunded, 3000, 'USD', '2026-09-22', 'refund');
        $this->charge($old, 3000, 'USD', '2026-07-01');
        $this->charge($trial, 0, 'USD', '2026-09-25');
        $this->charge($euro, 2799, 'EUR', '2026-09-12');

        // Reporting currency (USD) only — the EUR sale is never added in.
        $this->assertSame(300.0, $revenue->revenue($this->range));
        $this->assertSame(4, $revenue->transactions($this->range), 'paid charges in any currency');
        $this->assertSame(1, $revenue->otherCurrencies($this->range));
        $this->assertSame(['count' => 1, 'amount' => 30.0], $revenue->refunds($this->range));
        $this->assertSame(4, $revenue->payingCustomers($this->range));
        $this->assertSame(100.0, $revenue->arpu($this->range), '300 USD over the 3 customers charged in USD');

        $sales = $revenue->salesByCurrency($this->range);
        $this->assertSame(['USD', 'EUR'], array_keys($sales));
        $this->assertSame([30000, 3000, 27000, 3], [$sales['USD']['gross']->minor, $sales['USD']['refunds']->minor, $sales['USD']['net']->minor, $sales['USD']['transactions']]);
        $this->assertSame([2799, 1], [$sales['EUR']['gross']->minor, $sales['EUR']['transactions']]);

        // Each paying USD subscription's latest charge, per month: 30 + 30 + 240/12. The trial,
        // the EUR subscription and the grant add nothing.
        $this->assertSame(80.0, $revenue->mrr());
        $this->assertSame(960.0, $revenue->arr());
        $this->assertSame(0.0, $revenue->mrr(Date::parse('2026-08-15')));

        $this->assertSame(['Pro' => 300.0], $revenue->revenueByPlan($this->range));
        $this->assertSame([$plan->id => 300.0], $revenue->revenueByPlanId($this->range));
        $this->assertSame([__('dashboard.billing.year') => 240.0, __('dashboard.billing.month') => 60.0], $revenue->revenueByBilling($this->range));
        $this->assertSame(['paid' => 4, 'trials' => 1, 'failed' => 0, 'refunded' => 1], $revenue->transactionOutcomes($this->range));
        $this->assertSame(240.0, $revenue->revenueSeries($this->range)['2026-09-15']);
    }

    public function test_churn_and_renewal_rates_ignore_admin_grants(): void
    {
        $subscriptions = new SubscriptionMetrics(new RevenueMetrics);
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 10]);

        // Five paid subscriptions live at the start of the window; one of them lapses during it.
        foreach (range(1, 4) as $i) {
            $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-11-01']);
        }
        $renewing = $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-11-01']);
        $this->charge($renewing, 1000, 'USD', '2026-09-01', 'renewal');
        $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-09-10', 'status' => 'expired']);

        // Free grants ending or cancelled in the window are not paying churn.
        $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-09-05', 'status' => 'expired', 'source' => 'admin']);
        $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-09-06', 'status' => 'expired', 'source' => 'promotional']);
        $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-10-01', 'status' => 'cancelled', 'cancelled_by' => 'user', 'source' => 'admin']);

        $this->assertSame(Format::share(1, 6), $subscriptions->churnRate($this->range));
        $this->assertSame(50.0, $subscriptions->renewalRate($this->range), 'one renewal against one lapsed paid subscription');
        $this->assertSame(1, $subscriptions->cancelled($this->range), 'the cancellations count still shows every cancellation');
        $this->assertSame(3, $subscriptions->expired($this->range));
    }

    public function test_mrr_uses_the_most_recently_dated_charge_not_the_last_inserted(): void
    {
        $revenue = new RevenueMetrics;
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 15, 'billing_interval' => 'month']);
        $subscription = $this->subscription($plan, $price, ['starts_at' => '2026-08-20', 'ends_at' => '2026-10-20']);

        $this->charge($subscription, 1500, 'USD', '2026-09-20', 'renewal');
        // An older renewal processed late (e.g. a reprocessed notification) gets the higher id.
        $this->charge($subscription, 999, 'USD', '2026-08-20');

        $this->assertSame(15.0, $revenue->mrr());
    }

    public function test_mrr_spreads_a_multi_period_charge_over_the_periods_it_covers(): void
    {
        $revenue = new RevenueMetrics;
        $plan = Plan::factory()->create();
        $monthly = PlanPrice::factory()->for($plan)->create(['amount' => 9.99, 'billing_interval' => 'month', 'billing_period' => 1]);
        $yearly = PlanPrice::factory()->for($plan)->create(['amount' => 120, 'billing_interval' => 'year', 'billing_period' => 1]);

        $upFront = $this->subscription($plan, $monthly, ['starts_at' => '2026-09-01', 'ends_at' => '2026-12-01']);
        $this->charge($upFront, 2400, 'USD', '2026-09-01')->update(['periods_covered' => 3]);
        $annual = $this->subscription($plan, $yearly, ['starts_at' => '2026-09-01', 'ends_at' => '2027-09-01']);
        $this->charge($annual, 12000, 'USD', '2026-09-01');

        $this->assertSame(18.0, $revenue->mrr(), '24.00 over three months + 120.00 over twelve');
        $this->assertSame(216.0, $revenue->arr());
    }

    public function test_mrr_never_counts_grants_trials_or_other_currencies_and_says_what_it_left_out(): void
    {
        $revenue = new RevenueMetrics;
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 10, 'billing_interval' => 'month']);

        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-01', 'ends_at' => '2026-10-01']), 1000, 'USD', '2026-09-01');
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-01', 'ends_at' => '2026-10-01', 'provider' => 'appstore']), 899, 'EUR', '2026-09-01');
        $this->subscription($plan, $price, ['starts_at' => '2026-09-01', 'ends_at' => '2026-12-01', 'source' => 'admin']);
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-25', 'ends_at' => '2026-10-25', 'status' => 'trialing', 'trial_ends_at' => '2026-10-02']), 0, 'USD', '2026-09-25');

        $this->assertSame(10.0, $revenue->mrr());
        $this->assertSame(1, $revenue->mrrOtherCurrencies(), 'the EUR subscription is reported as left out, not as zero');
    }

    public function test_counts_shown_beside_money_use_the_same_currency_scope(): void
    {
        $revenue = new RevenueMetrics;
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create();
        $usd = $this->subscription($plan, $price, ['starts_at' => '2026-09-10', 'ends_at' => '2026-10-10']);
        $eur = $this->subscription($plan, $price, ['starts_at' => '2026-09-10', 'ends_at' => '2026-10-10']);
        $this->charge($usd, 1000, 'USD', '2026-09-10');
        $this->charge($usd, 1000, 'USD', '2026-09-11', 'refund');
        $this->charge($eur, 900, 'EUR', '2026-09-10');
        $this->charge($eur, 900, 'EUR', '2026-09-11', 'refund');

        $this->assertSame(2, $revenue->transactions($this->range), 'all currencies');
        $this->assertSame(1, $revenue->transactions($this->range, 'USD'));
        $this->assertSame(['count' => 1, 'amount' => 10.0], $revenue->refunds($this->range), 'count and amount are both USD');
        $this->assertSame(2, $revenue->transactionOutcomes($this->range)['refunded'], 'outcome counts span every currency');
    }

    public function test_the_mrr_kpi_names_subscriptions_left_out_in_other_currencies(): void
    {
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 10, 'billing_interval' => 'month']);
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-01', 'ends_at' => '2026-10-01']), 1000, 'USD', '2026-09-01');
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-01', 'ends_at' => '2026-10-01']), 899, 'EUR', '2026-09-01');

        $metric = app(MonthlyRecurringRevenue::class)->build($this->range);

        $this->assertSame(10.0, $metric->value);
        $this->assertSame(__('dashboard.kpis.arr_other_currencies', ['value' => Format::currency(120), 'currency' => 'USD', 'count' => 1]), $metric->description);
    }

    public function test_the_revenue_kpi_counts_only_reporting_currency_transactions_under_its_amount(): void
    {
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create();
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-10']), 1000, 'USD', '2026-09-10');
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-10']), 900, 'EUR', '2026-09-10');
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-10']), 900, 'EUR', '2026-09-11');

        $metric = app(RevenueKpi::class)->build($this->range);

        $this->assertSame(__('dashboard.kpis.transactions_other_currencies', ['count' => '1', 'currency' => 'USD', 'currencies' => 1]), $metric->description);
    }

    public function test_unit_economics_scope_paying_customers_to_the_currency_of_arpu(): void
    {
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create();
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-10']), 1000, 'USD', '2026-09-10');
        $this->charge($this->subscription($plan, $price, ['starts_at' => '2026-09-10']), 900, 'EUR', '2026-09-10');

        $figures = collect(app(RevenueSection::class)->build($this->range))
            ->flatMap(fn ($row) => $row->blocks)
            ->first(fn ($block) => $block instanceof KeyFigures)
            ->figures;
        $paying = collect($figures)->firstWhere('label', __('dashboard.figures.paying_customers'));

        $this->assertSame(1, $paying['value']);
        $this->assertSame(__('dashboard.figures.paying_customers_hint', ['currency' => 'USD']), $paying['hint']);
    }

    public function test_renewals_count_in_the_month_they_were_charged(): void
    {
        $revenue = new RevenueMetrics;
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 10, 'billing_interval' => 'month']);
        $subscription = $this->subscription($plan, $price, ['starts_at' => '2026-06-01', 'ends_at' => '2026-10-01']);

        $this->charge($subscription, 1000, 'USD', '2026-06-01');
        $this->charge($subscription, 1000, 'USD', '2026-09-01', 'renewal');

        $this->assertSame(10.0, $revenue->revenue($this->range), 'only the September renewal falls in the window');
        $this->assertSame(10.0, $revenue->mrr());
    }

    public function test_mrr_uses_the_latest_charge_not_the_current_list_price(): void
    {
        $revenue = new RevenueMetrics;
        $plan = Plan::factory()->create();
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 15, 'billing_interval' => 'month']);
        $subscription = $this->subscription($plan, $price, ['starts_at' => '2026-09-01', 'ends_at' => '2026-10-01']);

        $this->charge($subscription, 999, 'USD', '2026-09-01');

        $this->assertSame(9.99, $revenue->mrr());
    }

    public function test_subscription_lifecycle_rates_ignore_system_replacements(): void
    {
        $subscriptions = new SubscriptionMetrics(new RevenueMetrics);
        $plan = Plan::factory()->create(['name' => 'Basic']);
        $price = PlanPrice::factory()->for($plan)->create(['amount' => 10]);

        // Four paid subscriptions live at the start of the window.
        foreach (range(1, 4) as $i) {
            $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-11-01']);
        }

        $this->travelTo(Date::parse('2026-09-10'));
        $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-09-09', 'status' => 'cancelled', 'cancelled_by' => 'user']);
        $this->subscription($plan, $price, ['starts_at' => '2026-08-01', 'ends_at' => '2026-09-09', 'status' => 'cancelled', 'cancelled_by' => 'system']);
        $this->travelTo(Date::parse('2026-09-28 12:00:00'));

        $this->subscription($plan, $price, ['starts_at' => '2026-07-01', 'ends_at' => '2026-09-05', 'status' => 'expired']);
        $this->subscription($plan, $price, ['starts_at' => '2026-09-01', 'ends_at' => '2026-10-01', 'status' => 'active', 'trial_ends_at' => '2026-09-08']);
        $this->subscription($plan, $price, ['starts_at' => '2026-09-01', 'ends_at' => '2026-09-08', 'status' => 'expired', 'trial_ends_at' => '2026-09-08']);

        $this->assertSame(1, $subscriptions->cancelled($this->range), 'A system replacement is not a customer leaving.');
        $this->assertSame(2, $subscriptions->expired($this->range));
        $this->assertSame(50.0, $subscriptions->trialConversionRate($this->range));
        $this->assertSame(['Basic' => 5], $subscriptions->planDistribution());
        $this->assertSame(2, $subscriptions->started($this->range));
        $this->assertGreaterThan(0, $subscriptions->churnRate($this->range));
    }

    public function test_support_response_times_and_agent_performance(): void
    {
        $support = new SupportMetrics;
        $agent = User::factory()->create(['type' => 'staff', 'name' => 'Sarah Agent']);
        $category = TicketCategory::factory()->create(['name' => 'Billing']);

        $answered = Ticket::factory()->create([
            'category_id' => $category->id,
            'assigned_to' => $agent->id,
            'status' => TicketStatus::Closed->value,
            'priority' => 'high',
            'created_at' => '2026-09-10 10:00',
            'closed_at' => '2026-09-10 16:00',
        ]);
        TicketMessage::factory()->create(['ticket_id' => $answered->id, 'author_type' => TicketMessageAuthorType::System->value, 'created_at' => '2026-09-10 10:01']);
        TicketMessage::factory()->fromStaff($agent)->create(['ticket_id' => $answered->id, 'created_at' => '2026-09-10 12:00']);

        Ticket::factory()->create([
            'category_id' => $category->id,
            'assigned_to' => $agent->id,
            'status' => TicketStatus::Open->value,
            'priority' => 'urgent',
            'created_at' => '2026-09-20 10:00',
        ]);
        Ticket::factory()->create(['category_id' => null, 'assigned_to' => null, 'status' => TicketStatus::Pending->value, 'priority' => 'low', 'created_at' => '2026-09-25 10:00']);

        $times = $support->responseTimes($this->range);

        $this->assertSame(120.0, $times['first_response'], 'System notes never count as a response.');
        $this->assertSame(360.0, $times['resolution']);
        $this->assertSame(33.3, $times['responded']);
        $this->assertSame(2, $support->backlog());
        $this->assertSame(1, $support->unassigned());
        $this->assertSame(1, $support->urgentBacklog());
        $this->assertSame(1, $support->resolved($this->range));
        $this->assertSame(['Billing' => 2, __('dashboard.support.uncategorised') => 1], $support->categoryBreakdown($this->range));

        $this->assertSame([[
            'agent' => 'Sarah Agent',
            'assigned' => 2,
            'resolved' => 1,
            'open' => 1,
            'first_response' => 120.0,
            'resolution' => 360.0,
        ]], $support->agentPerformance($this->range));

        $volume = $support->volumeSeries($this->range);
        $this->assertSame(1, $volume['created']['2026-09-10']);
        $this->assertSame(1, $volume['resolved']['2026-09-10']);
    }

    public function test_security_sign_ins_suspensions_and_spikes(): void
    {
        $security = new SecurityMetrics;
        $user = $this->appUser(['created_at' => '2026-01-01']);

        $this->loginAt($user, '2026-09-20 08:00');
        $this->loginAt($user, '2026-09-21 08:00', passkey: true);

        foreach (range(1, 30) as $i) {
            $this->travelTo(Date::parse('2026-09-22 03:00')->addMinutes($i));
            ActivityLogger::log(ActivityModule::User, ActivityAction::Failed, properties: ['email' => "x{$i}@example.com"], causer: null, logName: ActivityLogName::Authentication);
        }
        $this->travelTo(Date::parse('2026-09-28 12:00:00'));

        $this->appUser(['created_at' => '2026-01-01', 'banned_at' => '2026-09-15']);
        $blocker = User::factory()->create(['type' => 'staff', 'banned_at' => null]);
        BlockedIp::factory()->create(['blocked_by' => $blocker->id]);
        BlockedIp::factory()->expired()->create(['blocked_by' => $blocker->id]);

        $this->assertSame(2, $security->successfulLogins($this->range));
        $this->assertSame(1, $security->passkeyLogins($this->range));
        $this->assertSame(30, $security->failedLogins($this->range));
        $this->assertSame(1, $security->suspensions($this->range));
        $this->assertSame(1, $security->activeBlockedIps());
        $this->assertSame(['2026-09-22' => 30], $security->failedLoginSpikes($this->range));
        $this->assertSame(30, $security->authSeries($this->range)['failed']['2026-09-22']);
        $this->assertCount(8, $security->recentEvents($this->range));
    }

    /** @param  array<string, mixed>  $attributes */
    private function appUser(array $attributes = []): User
    {
        return User::factory()->create([
            'type' => 'app',
            'banned_at' => null,
            'email_verified_at' => null,
            'google_id' => null,
            'apple_id' => null,
            'deletion_requested_at' => null,
            ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function subscription(Plan $plan, PlanPrice $price, array $attributes = []): Subscription
    {
        return Subscription::factory()->create([
            'user_id' => $this->appUser()->id,
            'plan_id' => $plan->id,
            'plan_price_id' => $price->id,
            'status' => 'active',
            ...$attributes,
        ]);
    }

    private function charge(Subscription $subscription, int $minor, string $currency, string $at, string $type = 'initial'): SubscriptionTransaction
    {
        return SubscriptionTransaction::factory()->for($subscription)->amount($minor, $currency)->create([
            'provider' => $subscription->provider,
            'type' => $type,
            'purchased_at' => $at,
        ]);
    }

    private function loginAt(User $user, string $at, bool $passkey = false): void
    {
        $now = Date::now();
        $this->travelTo(Date::parse($at));

        ActivityLogger::log(ActivityModule::User, ActivityAction::Login, $user, properties: $passkey ? ['area' => 'passkey'] : [], causer: $user, logName: ActivityLogName::Authentication);

        $this->travelTo($now);
    }
}
