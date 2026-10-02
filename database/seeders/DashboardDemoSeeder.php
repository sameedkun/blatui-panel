<?php

namespace Database\Seeders;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityLogName;
use App\Enum\ActivityModule;
use App\Enum\ReportFormat;
use App\Enum\ReportFrequency;
use App\Enum\SubscriptionSource;
use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketStatus;
use App\Models\BlockedIp;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Report\GeneratedReport;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ApiLog\AggregationService;
use App\Services\Report\ReportService;
use App\Support\Dashboard\DashboardCache;
use App\Support\Dashboard\DashboardRegistry;
use App\Support\Dashboard\DateRange;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * A year of realistic, deterministic demo data so every Dashboard page —
 * Overview, all six Analytics tabs and Reports — has something to show.
 *
 *     php artisan db:seed --class=DashboardDemoSeeder
 *
 * Local/testing only (refuses to run in production). Every demo account uses
 * the `@dashboard-demo.test` domain, and the seeder skips itself when those
 * already exist — run `migrate:fresh --seed` to start over.
 *
 * High-volume history (sign-ins, audit entries, API request logs, receipts)
 * is bulk inserted with explicit timestamps rather than replayed through the
 * app, so a year of data seeds in seconds; everything else goes through
 * factories and the real services (ReportService, AggregationService).
 */
class DashboardDemoSeeder extends Seeder
{
    public const string EMAIL_DOMAIN = 'dashboard-demo.test';

    private CarbonInterface $now;

    private User $admin;

    /** @var list<array<string, mixed>> */
    private array $activity = [];

    private string $userMorph;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DashboardDemoSeeder never runs in production.');

            return;
        }

        if (User::withTrashed()->where('email', 'like', '%@'.self::EMAIL_DOMAIN)->exists()) {
            $this->command?->warn('Dashboard demo data already exists — skipping. Use `migrate:fresh --seed` to rebuild it.');

            return;
        }

        // Called from DatabaseSeeder (WithoutModelEvents) model events are off,
        // but ULIDs/external ids are generated in `creating` hooks — turn them back on.
        $dispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(app('events'));

        try {
            mt_srand(2026);
            fake()->seed(2026);

            $this->now = Date::now();
            $this->userMorph = (new User)->getMorphClass();
            $this->admin = $this->admin();

            $prices = $this->prices();
            $agents = $this->agents();
            $categories = $this->categories($agents);
            $users = $this->users();

            $this->guests($users);
            $this->signIns($users);
            $this->failedSignIns();
            $this->subscriptions($users, $prices);
            $this->tickets($users, $agents, $categories);
            $this->devices($users);
            $this->securityEvents($users);
            $this->adminActivity($users);
            $this->flushActivity();

            $this->call(DeviceManagementDemoSeeder::class);
            $this->apiTraffic($users);
            $this->reports();

            app(DashboardCache::class)->flush();

            $this->command?->info(sprintf('Dashboard demo data seeded: %d app users, %d subscriptions, %d tickets.',
                $users->count(),
                Subscription::query()->whereIn('user_id', $users->pluck('id'))->count(),
                Ticket::query()->count(),
            ));
        } finally {
            Model::setEventDispatcher($dispatcher);
        }
    }

    // ── People ─────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::query()->where('email', config('panel.admin.email'))->first()
            ?? User::query()->staff()->orderBy('id')->first()
            ?? User::factory()->create([
                'type' => 'staff',
                'name' => 'Demo Admin',
                'email' => 'admin@'.self::EMAIL_DOMAIN,
                'banned_at' => null,
            ]);
    }

    /** @return Collection<int, User> three support agents with ticket access */
    private function agents(): Collection
    {
        $role = Role::firstOrCreate(['name' => 'support-agent', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::query()->whereIn('name', [
            'panel.access-admin', 'dashboard.view', 'tickets.view', 'tickets.create', 'tickets.manage',
        ])->get());

        return collect(['Sarah Khan', 'John Miller', 'Aisha Malik'])->map(function (string $name) use ($role): User {
            $agent = User::factory()->create([
                'type' => 'staff',
                'name' => $name,
                'email' => Str::slug($name, '.').'@'.self::EMAIL_DOMAIN,
                'email_verified_at' => $this->now->subYear(),
                'banned_at' => null,
                'google_id' => null,
                'apple_id' => null,
                'created_at' => $this->now->subYear(),
            ]);
            $agent->assignRole($role);

            return $agent;
        });
    }

    /**
     * App users signing up over the last 12 months, growing month on month.
     *
     * @return Collection<int, User>
     */
    private function users(): Collection
    {
        $users = collect();
        $n = 0;

        for ($monthsAgo = 11; $monthsAgo >= 0; $monthsAgo--) {
            $monthStart = $this->now->subMonthsNoOverflow($monthsAgo)->startOfMonth();
            $monthEnd = $monthsAgo === 0 ? $this->now : $monthStart->endOfMonth();
            $signups = 14 + (11 - $monthsAgo) * 4 + mt_rand(-3, 5);

            for ($i = 0; $i < $signups; $i++) {
                $createdAt = $this->between($monthStart, $monthEnd);
                $method = $this->pick(['email' => 70, 'google' => 20, 'apple' => 10]);
                $name = fake()->name();

                $users->push(User::factory()->create([
                    'type' => 'app',
                    'name' => $name,
                    'email' => Str::slug($name, '.').'.'.(++$n).'@'.self::EMAIL_DOMAIN,
                    'email_verified_at' => $this->chance(0.78) ? $createdAt->addMinutes(mt_rand(1, 600)) : null,
                    'google_id' => $method === 'google' ? 'demo-google-'.$n : null,
                    'apple_id' => $method === 'apple' ? 'demo-apple-'.$n : null,
                    'banned_at' => null,
                    'ban_reason' => null,
                    'last_login' => null,
                    'registration_date' => $createdAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]));
            }
        }

        return $users;
    }

    /** Guests, some of whom converted into the app users created above. */
    private function guests(Collection $users): void
    {
        for ($i = 0; $i < 70; $i++) {
            $createdAt = $this->between($this->now->subDays(90), $this->now);

            User::factory()->guest()->create([
                'name' => 'Guest '.Str::upper(Str::random(6)),
                'email' => 'guest_'.Str::lower(Str::random(10)).'@'.self::EMAIL_DOMAIN,
                'email_verified_at' => null,
                'banned_at' => null,
                'google_id' => null,
                'apple_id' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $users->filter(fn (User $user): bool => $user->created_at->greaterThan($this->now->subDays(90)))
            ->shuffle()
            ->take(24)
            ->each(fn (User $user) => $this->log(ActivityLogName::Audit, ActivityModule::Guest, ActivityAction::Converted, $user, $user, [
                'provider' => $this->pick(['email' => 60, 'google' => 30, 'apple' => 10]),
            ], $user->created_at->addMinutes(2)));
    }

    // ── Authentication history ─────────────────────────────────────────────

    private function signIns(Collection $users): void
    {
        foreach ($users as $user) {
            // Engagement decays for some users, so retention and "active users" aren't flat.
            $engaged = $this->chance(0.65);
            $logins = $engaged ? mt_rand(4, 26) : mt_rand(0, 3);
            $last = null;

            for ($i = 0; $i < $logins; $i++) {
                $from = $engaged ? max($user->created_at->timestamp, $this->now->subDays(120)->timestamp) : $user->created_at->timestamp;
                $to = $engaged ? $this->now->timestamp : min($this->now->timestamp, $user->created_at->addDays(20)->timestamp);
                $at = Date::createFromTimestamp(mt_rand($from, max($from, $to)));

                $this->log(ActivityLogName::Authentication, ActivityModule::User, ActivityAction::Login, $user, $user, [
                    'ip' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                ], $at);

                $last = $last === null || $at->greaterThan($last) ? $at : $last;
            }

            if ($last !== null) {
                $user->forceFill(['last_login' => $last])->saveQuietly();
            }
        }

        // Staff sign-ins, some with a passkey.
        for ($day = 0; $day < 30; $day++) {
            $this->log(ActivityLogName::Authentication, ActivityModule::Staff, ActivityAction::Login, $this->admin, $this->admin,
                $this->chance(0.4) ? ['area' => 'passkey'] : [],
                $this->now->subDays($day)->setTime(9, mt_rand(0, 59)));
        }
    }

    /** A steady trickle of failed sign-ins, plus one credential-stuffing spike. */
    private function failedSignIns(): void
    {
        for ($day = 0; $day < 120; $day++) {
            $count = $day === 6 ? 160 : mt_rand(1, 9);
            $date = $this->now->subDays($day)->startOfDay();

            for ($i = 0; $i < $count; $i++) {
                $this->log(ActivityLogName::Authentication, ActivityModule::User, ActivityAction::Failed, null, null, [
                    'email' => fake()->safeEmail(),
                    'reason' => $this->chance(0.6) ? 'Invalid password' : 'Account not found',
                    'ip' => $day === 6 ? '203.0.113.'.mt_rand(1, 20) : fake()->ipv4(),
                ], $date->addSeconds(mt_rand(0, 86_399)));
            }
        }
    }

    // ── Billing ────────────────────────────────────────────────────────────

    /** @return array<string, PlanPrice> keyed starter_month / pro_month / pro_year / business_month */
    private function prices(): array
    {
        if (! Plan::query()->where('slug', 'pro')->exists()) {
            $this->call(PlansSeeder::class);
        }

        $price = fn (string $slug, string $interval): PlanPrice => PlanPrice::query()
            ->whereHas('plan', fn ($query) => $query->where('slug', $slug))
            ->where('billing_interval', $interval)
            ->firstOrFail();

        return [
            'starter_month' => $price('starter', 'month'),
            'pro_month' => $price('pro', 'month'),
            'pro_year' => $price('pro', 'year'),
            'business_month' => $price('business', 'month'),
        ];
    }

    /**
     * What a store charges for a USD list price in a few local storefronts —
     * demo-only rates so "Sales by currency" has more than one row.
     */
    private const array DEMO_LOCAL_PRICE_RATES = ['EUR' => 0.92, 'GBP' => 0.79, 'PKR' => 280.0];

    /**
     * One subscription "contract" per user (renewals are transactions, as in
     * the app itself), spread across every lifecycle state. Local ones are
     * free admin grants with no transactions; store ones are sometimes
     * charged in a local currency.
     *
     * @param  array<string, PlanPrice>  $prices
     */
    private function subscriptions(Collection $users, array $prices): void
    {
        $transactions = [];

        foreach ($users as $user) {
            if (! $this->chance(0.48)) {
                continue;
            }

            $price = $prices[$this->pick(['starter_month' => 35, 'pro_month' => 35, 'pro_year' => 15, 'business_month' => 15])];
            $months = $price->billing_interval->value === 'year' ? 12 : 1;
            $provider = $this->pick(['local' => 20, 'stripe' => 40, 'appstore' => 25, 'playstore' => 15]);
            $startsAt = $this->between($user->created_at->addHour(), min($this->now, $user->created_at->addDays(45)));
            $state = $this->pick(['active' => 55, 'cancelled' => 14, 'expired' => 14, 'grace' => 4, 'failed' => 3, 'trialing' => 10]);
            $currency = in_array($provider, ['appstore', 'playstore'], true)
                ? $this->pick(['USD' => 70, 'EUR' => 12, 'GBP' => 10, 'PKR' => 8])
                : 'USD';

            if ($state === 'trialing' && $startsAt->lessThan($this->now->subDays(6))) {
                $state = 'active';
            }

            $periods = 1;
            $endsAt = $startsAt->addMonthsNoOverflow($months);
            while ($endsAt->lessThanOrEqualTo($this->now) && in_array($state, ['active', 'cancelled'], true)) {
                $endsAt = $endsAt->addMonthsNoOverflow($months);
                $periods++;
            }

            $attributes = match ($state) {
                'trialing' => ['trial_ends_at' => $startsAt->addDays(7), 'ends_at' => $startsAt->addDays(7)->addMonth()],
                'grace' => ['ends_at' => $this->now->subDays(mt_rand(1, 3)), 'grace_ends_at' => $this->now->addDays(mt_rand(1, 4))],
                'expired' => ['ends_at' => min($endsAt, $this->now->subDays(mt_rand(1, 60))), 'is_recurring' => false],
                'failed' => ['ends_at' => $startsAt],
                'cancelled' => [
                    'cancelled_by' => $this->pick(['user' => 85, 'admin' => 15]),
                    'cancelled_reason' => $this->pick(['Too expensive' => 40, 'Not using it enough' => 35, 'Switching provider' => 25]),
                    'is_recurring' => false,
                    'ends_at' => $endsAt,
                    'updated_at' => $this->between(max($startsAt, $this->now->subDays(80)), $this->now),
                ],
                default => ['ends_at' => $endsAt],
            };

            $subscription = Subscription::factory()->create([
                'user_id' => $user->id,
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'starts_at' => $startsAt,
                'trial_ends_at' => null,
                'grace_ends_at' => null,
                'status' => $state,
                'cancelled_by' => null,
                'cancelled_reason' => null,
                'is_recurring' => true,
                'provider' => $provider,
                'source' => $provider === 'local' ? SubscriptionSource::Admin : SubscriptionSource::Purchase,
                'grant_reason' => $provider === 'local' ? $this->pick(['Support compensation' => 50, 'Partner account' => 30, 'Beta tester' => 20]) : null,
                'created_at' => $startsAt,
                'updated_at' => $startsAt,
                ...$attributes,
            ]);

            if ($state === 'failed' || $state === 'trialing' || $provider === 'local') {
                continue;
            }

            $charge = $this->demoCharge($price, $currency);
            $transactions[] = $this->transaction($subscription, 'initial', $charge, $startsAt);

            for ($p = 1; $p < $periods; $p++) {
                $transactions[] = $this->transaction($subscription, 'renewal', $charge, $startsAt->addMonthsNoOverflow($months * $p));
            }

            if ($this->chance(0.04)) {
                $transactions[] = $this->transaction($subscription, 'refund', $charge, $startsAt->addDays(mt_rand(1, 10)));
            }
        }

        $transactions = array_filter($transactions, fn (array $row): bool => $row['purchased_at'] <= $this->now->toDateTimeString());

        foreach (array_chunk($transactions, 500) as $chunk) {
            DB::table('subscription_transactions')->insert($chunk);
        }
    }

    /** The demo price of `$price` in `$currency` (USD list prices, rough local storefront prices otherwise). */
    private function demoCharge(PlanPrice $price, string $currency): Money
    {
        $amount = (float) $price->amount * (self::DEMO_LOCAL_PRICE_RATES[$currency] ?? 1.0);

        return Money::ofMajor(number_format($currency === 'PKR' ? round($amount, -2) : $amount, 2, '.', ''), $currency);
    }

    /** @return array<string, mixed> */
    private function transaction(Subscription $subscription, string $type, Money $amount, CarbonInterface $at): array
    {
        return [
            'subscription_id' => $subscription->id,
            'provider' => $subscription->provider->value,
            'type' => $type,
            'amount_minor' => $amount->minor,
            'currency' => $amount->currency,
            'purchased_at' => $at->toDateTimeString(),
            'provider_transaction_id' => $transactionId = 'demo_'.Str::lower(Str::random(16)),
            'idempotency_key' => "{$type}:{$transactionId}",
            'provider_original_id' => 'demo_orig_'.$subscription->id,
            'payload' => null,
            'created_at' => $at->toDateTimeString(),
            'updated_at' => $at->toDateTimeString(),
        ];
    }

    // ── Support ────────────────────────────────────────────────────────────

    /** @return Collection<int, TicketCategory> */
    private function categories(Collection $agents): Collection
    {
        return collect(['Billing', 'Technical', 'Account', 'Feature Requests'])->map(function (string $name, int $index) use ($agents): TicketCategory {
            $category = TicketCategory::query()->firstOrCreate(['name' => $name], ['is_active' => true, 'sort_order' => $index]);
            $category->agents()->syncWithoutDetaching($agents->pluck('id')->all());

            return $category;
        });
    }

    private function tickets(Collection $users, Collection $agents, Collection $categories): void
    {
        $subjects = [
            'Billing' => ['I was charged twice this month', 'How do I get an invoice?', 'Refund request for my renewal', 'Card declined on renewal'],
            'Technical' => ['App crashes on launch', 'Cannot connect since the update', 'Sync is stuck', 'Notifications stopped working'],
            'Account' => ['Can\'t sign in with Google', 'Please delete my account', 'Change my email address', 'Two-factor code not arriving'],
            'Feature Requests' => ['Dark mode on Android', 'Export my data to CSV', 'Family sharing plan', 'Widget for the home screen'],
        ];
        // Each agent answers at their own pace, so the performance table differs per agent.
        $speed = [$agents[0]->id => 1.0, $agents[1]->id => 1.8, $agents[2]->id => 0.7];

        for ($i = 0; $i < 170; $i++) {
            // Skewed towards recent tickets, reaching back ~120 days.
            $createdAt = $this->now->subMinutes((int) ((mt_rand() / mt_getrandmax()) ** 1.6 * 120 * 1440));
            $age = $createdAt->diffInDays($this->now);
            $status = $age > 10
                ? $this->pick(['closed' => 70, 'resolved' => 20, 'pending' => 6, 'open' => 4])
                : $this->pick(['open' => 40, 'pending' => 30, 'resolved' => 20, 'closed' => 10]);
            $category = $this->chance(0.92) ? $categories->random() : null;
            $agent = $this->chance(0.88) ? $agents->random() : null;
            $requester = $users->random();

            $answered = $agent !== null && ($status !== 'open' || $this->chance(0.4));
            $firstReply = $answered ? $createdAt->addMinutes((int) (mt_rand(10, 900) * $speed[$agent->id])) : null;
            $firstReply = $firstReply !== null && $firstReply->greaterThan($this->now) ? null : $firstReply;
            $resolvedAt = in_array($status, ['closed', 'resolved'], true)
                ? min($this->now, ($firstReply ?? $createdAt)->addMinutes((int) (mt_rand(60, 4000) * ($agent ? $speed[$agent->id] : 1))))
                : null;

            $ticket = Ticket::factory()->create([
                'user_id' => $requester->id,
                'category_id' => $category?->id,
                'assigned_to' => $agent?->id,
                'subject' => fake()->randomElement($subjects[$category->name ?? 'Technical']),
                'status' => $status,
                'priority' => $this->pick(['low' => 25, 'medium' => 45, 'high' => 22, 'urgent' => 8]),
                'last_user_response_at' => $createdAt,
                'last_staff_response_at' => $firstReply,
                'closed_at' => $status === TicketStatus::Closed->value ? $resolvedAt : null,
                'created_at' => $createdAt,
                'updated_at' => $resolvedAt ?? $firstReply ?? $createdAt,
            ]);

            TicketMessage::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $requester->id,
                'author_type' => TicketMessageAuthorType::User->value,
                'message' => fake()->paragraph(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            if ($agent !== null) {
                TicketMessage::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => null,
                    'author_type' => TicketMessageAuthorType::System->value,
                    'message' => "Automatically assigned to {$agent->name}.",
                    'created_at' => $createdAt->addSecond(),
                    'updated_at' => $createdAt->addSecond(),
                ]);
            }

            if ($firstReply !== null) {
                TicketMessage::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $agent->id,
                    'author_type' => TicketMessageAuthorType::Staff->value,
                    'message' => 'Thanks for reaching out — '.fake()->sentence(12),
                    'created_at' => $firstReply,
                    'updated_at' => $firstReply,
                ]);

                $this->log(ActivityLogName::Audit, ActivityModule::Ticket, ActivityAction::Replied, $ticket, $agent, [], $firstReply);
            }
        }
    }

    // ── Devices & security ─────────────────────────────────────────────────

    private function devices(Collection $users): void
    {
        $users->shuffle()->take((int) ($users->count() * 0.45))->each(function (User $user): void {
            $createdAt = $this->between($user->created_at, $this->now);
            $state = $this->pick(['active' => 85, 'revoked' => 11, 'blocked' => 4]);
            $changedAt = $this->between($createdAt, $this->now);

            $device = UserDevice::factory()->create([
                'user_id' => $user->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'last_seen_at' => $this->between($createdAt, $this->now),
                'revoked_at' => $state === 'revoked' ? $changedAt : null,
                'blocked_at' => $state === 'blocked' ? $changedAt : null,
                'blocked_reason' => $state === 'blocked' ? 'Demo: suspicious sharing pattern.' : null,
            ]);

            if ($state !== 'active') {
                $action = $state === 'revoked' ? ActivityAction::Revoked : ActivityAction::Blocked;
                $this->log(ActivityLogName::Audit, ActivityModule::Device, $action, $device, $this->admin, ['device_name' => $device->name], $changedAt);
            }
        });

        foreach (range(1, 6) as $i) {
            $createdAt = $this->between($this->now->subDays(60), $this->now);

            BlockedIp::factory()->create([
                'ip_address' => '198.18.'.mt_rand(0, 255).'.'.$i,
                'blocked_by' => $this->admin->id,
                'hits' => mt_rand(0, 400),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'expires_at' => $this->chance(0.5) ? null : $this->now->addDays(mt_rand(1, 30)),
            ]);
        }
    }

    /** Suspensions, password changes and pending deletions, each with its audit entry. */
    private function securityEvents(Collection $users): void
    {
        $users->shuffle()->take(12)->each(function (User $user): void {
            $at = $this->between(max($user->created_at, $this->now->subDays(200)), $this->now);
            $reason = $this->pick(['Chargeback fraud' => 30, 'Abusive support messages' => 30, 'Account sharing' => 40]);
            $user->forceFill(['banned_at' => $at, 'ban_reason' => $reason])->saveQuietly();
            $this->log(ActivityLogName::Audit, ActivityModule::User, ActivityAction::Banned, $user, $this->admin, ['ban_reason' => $reason], $at);
        });

        $users->shuffle()->take(45)->each(function (User $user): void {
            $at = $this->between($user->created_at, $this->now);
            $user->forceFill(['password_changed_at' => $at])->saveQuietly();

            if ($this->chance(0.5)) {
                $this->log(ActivityLogName::Authentication, ActivityModule::User, ActivityAction::PasswordReset, $user, $user, [], $at);
            }
        });

        $users->whereNull('banned_at')->shuffle()->take(4)->each(function (User $user): void {
            $user->forceFill([
                'deletion_requested_at' => $this->now->subHours(mt_rand(1, 20)),
                'deletion_requested_by' => 'user',
                'deletion_reason' => 'No longer need the service.',
            ])->saveQuietly();
        });
    }

    /** A little recent admin activity so the Overview feed reads like a live panel. */
    private function adminActivity(Collection $users): void
    {
        $users->sortByDesc('created_at')->take(4)->values()->each(fn (User $user, int $i) => $this->log(
            ActivityLogName::Audit, ActivityModule::User, ActivityAction::Updated, $user, $this->admin,
            ['attributes' => ['name' => $user->name]], $this->now->subMinutes(15 + $i * 40),
        ));

        $this->log(ActivityLogName::Audit, ActivityModule::Plan, ActivityAction::Updated, Plan::query()->where('slug', 'pro')->first(), $this->admin,
            ['attributes' => ['is_best_deal' => true]], $this->now->subHours(5));
    }

    // ── API traffic ────────────────────────────────────────────────────────

    /** 30 days of API requests, then rolled up exactly as the scheduler would. */
    private function apiTraffic(Collection $users): void
    {
        // [method, route, typical latency ms, relative traffic weight]
        $routes = [
            ['GET', 'api/v1/me', 45, 26], ['GET', 'api/v1/subscription', 60, 12], ['GET', 'api/v1/plans', 30, 10],
            ['POST', 'api/v1/login', 180, 8], ['GET', 'api/v1/devices', 55, 6], ['GET', 'api/v1/tickets', 70, 5],
            ['POST', 'api/v1/tickets', 140, 2], ['POST', 'api/v1/tickets/{ticket}/reply', 120, 2],
            ['PUT', 'api/v1/me', 90, 3], ['GET', 'api/v1/languages', 25, 4], ['POST', 'api/v1/signup', 210, 2],
            ['POST', 'api/v1/feedback', 80, 1],
        ];
        $weights = array_column($routes, 3);
        $rows = [];

        for ($day = 29; $day >= 0; $day--) {
            $volume = 180 + (29 - $day) * 6 + mt_rand(-20, 20);

            for ($i = 0; $i < $volume; $i++) {
                $at = $this->now->subDays($day)->startOfDay()->addSeconds(mt_rand(0, 86_399));

                if ($at->greaterThan($this->now)) {
                    continue;
                }

                [$method, $uri, $baseMs] = $routes[$this->weightedIndex($weights)];
                $status = match (true) {
                    $uri === 'api/v1/login' && $this->chance(0.12) => 422,
                    $this->chance(0.006) => (int) $this->pick(['500' => 70, '503' => 30]),
                    $this->chance(0.025) => (int) $this->pick(['401' => 40, '404' => 30, '422' => 30]),
                    default => $method === 'POST' ? 201 : 200,
                };
                $user = $this->chance(0.8) ? $users->random() : null;

                $rows[] = [
                    'request_id' => 'req_'.Str::ulid(),
                    'correlation_id' => 'cor_'.Str::ulid(),
                    'method' => $method,
                    'path' => '/'.str_replace('{ticket}', (string) mt_rand(1, 170), $uri),
                    'route_uri' => $uri,
                    'api_version' => 'v1',
                    'status_code' => $status,
                    'status_class' => intdiv($status, 100),
                    'duration_ms' => round($baseMs * exp($this->gaussian() * 0.45) * ($status >= 500 ? 4 : 1), 2),
                    'memory_peak_kb' => mt_rand(8_000, 40_000),
                    'db_query_count' => mt_rand(1, 14),
                    'db_time_ms' => round(mt_rand(0, 4000) / 100, 2),
                    'request_size' => $method === 'GET' ? 0 : mt_rand(80, 2000),
                    'response_size' => mt_rand(120, 6000),
                    'ip' => fake()->ipv4(),
                    'user_agent' => 'DemoApp/2.4 ('.$this->pick(['iOS' => 55, 'Android' => 45]).')',
                    'client_type' => $this->pick(['app' => 85, 'web' => 15]),
                    'user_id' => $user?->id,
                    'user_type' => $user ? 'app' : null,
                    'error_code' => $status === 401 ? 'UNAUTHENTICATED' : null,
                    'has_exception' => $status >= 500,
                    'sample_weight' => 1,
                    'created_at' => $at->format('Y-m-d H:i:s.v'),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('api_request_logs')->insert($chunk);
        }

        app(AggregationService::class)->rebuildRange($this->now->subDays(31), $this->now);
    }

    // ── Reports ────────────────────────────────────────────────────────────

    private function reports(): void
    {
        $service = app(ReportService::class);
        $registry = app(DashboardRegistry::class);

        $service->saveSchedule([
            'name' => 'Monthly revenue',
            'report' => 'revenue',
            'format' => ReportFormat::Pdf,
            'frequency' => ReportFrequency::Monthly,
            'day_of_month' => 1,
            'hour' => 8,
            'recipients' => ['finance@'.self::EMAIL_DOMAIN],
        ], actor: $this->admin);

        $service->saveSchedule([
            'name' => 'Weekly sign-ups',
            'report' => 'users',
            'format' => ReportFormat::Xlsx,
            'frequency' => ReportFrequency::Weekly,
            'day_of_week' => 1,
            'hour' => 9,
            'recipients' => ['growth@'.self::EMAIL_DOMAIN],
        ], actor: $this->admin);

        // Real reports, built by the queue (immediately under QUEUE_CONNECTION=sync).
        $lastMonth = $this->now->subMonthNoOverflow();
        $service->request($registry->report('revenue'), DateRange::between($lastMonth->startOfMonth(), $lastMonth->endOfMonth()), [], ReportFormat::Pdf, $this->admin);
        $service->request($registry->report('support_performance'), DateRange::preset('30d'), [], ReportFormat::Xlsx, $this->admin);

        GeneratedReport::factory()->failed('Demo: the queue worker timed out.')->create([
            'report' => 'tickets',
            'title' => 'Support tickets',
            'requested_by' => $this->admin->id,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Queue an audit row with an explicit timestamp (flushed in bulk).
     *
     * @param  array<string, mixed>  $properties
     */
    private function log(ActivityLogName $logName, ActivityModule $module, ActivityAction $action, ?Model $subject, ?User $causer, array $properties, CarbonInterface $at): void
    {
        if ($at->greaterThan($this->now)) {
            return;
        }

        $this->activity[] = [
            'log_name' => $logName->value,
            'description' => $action->value,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $action->value,
            'causer_type' => $causer ? $this->userMorph : null,
            'causer_id' => $causer?->id,
            'properties' => json_encode([...$properties, 'module' => $module->value, 'context' => ActivityContext::Admin->value]),
            'created_at' => $at->toDateTimeString(),
            'updated_at' => $at->toDateTimeString(),
        ];
    }

    /** Insert the queued audit rows oldest first, so ids follow time as live logging would. */
    private function flushActivity(): void
    {
        usort($this->activity, fn (array $a, array $b): int => strcmp($a['created_at'], $b['created_at']));

        foreach (array_chunk($this->activity, 500) as $chunk) {
            DB::table('activity_log')->insert($chunk);
        }

        $this->activity = [];
    }

    private function between(CarbonInterface $from, CarbonInterface $to): CarbonInterface
    {
        return Date::createFromTimestamp(mt_rand($from->timestamp, max($from->timestamp, $to->timestamp)));
    }

    private function chance(float $probability): bool
    {
        return mt_rand() / mt_getrandmax() < $probability;
    }

    /**
     * @param  array<string, int>  $weights  option => weight
     */
    private function pick(array $weights): string
    {
        return (string) array_keys($weights)[$this->weightedIndex(array_values($weights))];
    }

    /** @param  list<int>  $weights */
    private function weightedIndex(array $weights): int
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $index => $weight) {
            if (($roll -= $weight) <= 0) {
                return $index;
            }
        }

        return array_key_last($weights);
    }

    /** Standard normal sample (Box–Muller), for natural-looking latencies. */
    private function gaussian(): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    }
}
