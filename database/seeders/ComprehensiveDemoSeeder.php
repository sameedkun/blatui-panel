<?php

namespace Database\Seeders;

use App\Enum\ActivityAction;
use App\Enum\ActivityContext;
use App\Enum\ActivityModule;
use App\Enum\ReceiptType;
use App\Enum\SubscriptionStatus;
use App\Enum\TicketMessageAuthorType;
use App\Enum\TicketStatus;
use App\Enum\UserType;
use App\Models\BlockedIp;
use App\Models\EmailDomain;
use App\Models\EmailSender;
use App\Models\Feedback;
use App\Models\Language;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use App\Models\Policy;
use App\Models\PolicyAcceptance;
use App\Models\PolicyVersion;
use App\Models\SmtpSetting;
use App\Models\Subscription;
use App\Models\SubscriptionReceipt;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\ActivityLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ComprehensiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        $staff = $this->seedStaff();
        $appUsers = $this->seedUsers();

        $categories = $this->seedTicketCategories($staff);
        $tickets = $this->seedTickets($appUsers, $staff, $categories);
        $this->seedDevices($appUsers);
        $this->seedBlockedIps($appUsers, $staff->first());

        $this->seedPlansAndSubscriptions($appUsers);
        $this->seedFeedback($appUsers);
        $this->seedNotifications();
        $this->seedLanguages();
        $this->seedPolicies($appUsers);
        $this->seedMailSettings();
        $this->seedActivity($staff->first(), $appUsers->first(), $tickets->first());
    }

    /** @return Collection<int, User> */
    private function seedStaff(): Collection
    {
        $staff = User::query()->staff()->get();

        if ($staff->count() >= 5) {
            return $staff;
        }

        return $staff->concat(User::factory(5 - $staff->count())->state([
            'type' => UserType::Staff,
            'banned_at' => null,
            'ban_reason' => null,
        ])->create());
    }

    /** @return Collection<int, User> */
    private function seedUsers(): Collection
    {
        $appUsers = User::query()->appUsers()->get();

        $appUsers = $appUsers->concat(User::factory(60)->app()->create());
        $appUsers = $appUsers->concat(User::factory(12)->app()->unverified()->create());
        $appUsers = $appUsers->concat(User::factory(10)->pendingDeletion('admin')->create());
        $appUsers = $appUsers->concat(User::factory(8)->app()->state([
            'banned_at' => now()->subDays(fake()->numberBetween(1, 120)),
            'ban_reason' => fake()->sentence(),
        ])->create());

        User::factory(20)->guest()->create();

        User::factory(6)->app()->create()->each->delete();

        return $appUsers;
    }

    /**
     * @param  Collection<int, User>  $staff
     * @return Collection<int, TicketCategory>
     */
    private function seedTicketCategories(Collection $staff): Collection
    {
        $categories = TicketCategory::factory(8)->create();
        $categories = $categories->concat(TicketCategory::factory(2)->inactive()->create());

        $categories->each(function (TicketCategory $category) use ($staff): void {
            $category->agents()->syncWithoutDetaching($staff->random(min(2, $staff->count()))->modelKeys());
        });

        return $categories;
    }

    /**
     * @param  Collection<int, User>  $appUsers
     * @param  Collection<int, User>  $staff
     * @param  Collection<int, TicketCategory>  $categories
     * @return Collection<int, Ticket>
     */
    private function seedTickets(Collection $appUsers, Collection $staff, Collection $categories): Collection
    {
        $tickets = collect();

        foreach (TicketStatus::cases() as $status) {
            foreach (range(1, 20) as $index) {
                $ticket = Ticket::factory()->create([
                    'user_id' => $appUsers->random()->id,
                    'category_id' => $categories->random()->id,
                    'assigned_to' => $index % 3 === 0 ? null : $staff->random()->id,
                    'status' => $status,
                    'last_user_response_at' => now()->subHours(fake()->numberBetween(1, 240)),
                    'last_staff_response_at' => $status === TicketStatus::Open ? null : now()->subHours(fake()->numberBetween(1, 120)),
                    'closed_at' => $status === TicketStatus::Closed ? now()->subDays(fake()->numberBetween(1, 90)) : null,
                ]);

                TicketMessage::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $ticket->user_id,
                    'author_type' => TicketMessageAuthorType::User,
                ]);

                if ($ticket->assigned_to !== null) {
                    TicketMessage::factory()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $ticket->assigned_to,
                        'author_type' => TicketMessageAuthorType::Staff,
                    ]);
                }

                if ($status->isTerminal()) {
                    TicketMessage::factory()->system('This ticket was resolved and archived for demo purposes.')->create([
                        'ticket_id' => $ticket->id,
                    ]);
                }

                $tickets->push($ticket);
            }
        }

        return $tickets;
    }

    /** @param Collection<int, User> $appUsers */
    private function seedDevices(Collection $appUsers): void
    {
        UserDevice::factory(80)->state(fn (): array => ['user_id' => $appUsers->random()->id])->create();
        UserDevice::factory(15)->revoked()->state(fn (): array => ['user_id' => $appUsers->random()->id])->create();
        UserDevice::factory(15)->blocked()->state(fn (): array => ['user_id' => $appUsers->random()->id])->create();
    }

    /**
     * @param  Collection<int, User>  $appUsers
     */
    private function seedBlockedIps(Collection $appUsers, User $staff): void
    {
        BlockedIp::factory(6)->global()->state(['blocked_by' => $staff->id])->create();
        BlockedIp::factory(6)->permanent()->state(['blocked_by' => $staff->id])->create();
        BlockedIp::factory(6)->expired()->state(['blocked_by' => $staff->id])->create();
        BlockedIp::factory(12)->state(fn (): array => [
            'user_id' => $appUsers->random()->id,
            'blocked_by' => $staff->id,
        ])->create();
    }

    /** @param Collection<int, User> $appUsers */
    private function seedPlansAndSubscriptions(Collection $appUsers): void
    {
        $plans = Plan::factory(4)->create();
        $prices = $plans->flatMap(function (Plan $plan): Collection {
            return PlanPrice::factory(2)->for($plan)->create()->each(function (PlanPrice $price): void {
                PlanPriceProvider::factory()->for($price)->create();
            });
        });

        foreach (SubscriptionStatus::cases() as $status) {
            foreach (range(1, 8) as $index) {
                $price = $prices->random();
                $subscription = Subscription::factory()->create([
                    'user_id' => $appUsers->random()->id,
                    'plan_id' => $price->plan_id,
                    'plan_price_id' => $price->id,
                    'status' => $status,
                    'ends_at' => $status === SubscriptionStatus::Active ? now()->addMonth() : now()->subDay(),
                    'trial_ends_at' => $status === SubscriptionStatus::Trialing ? now()->addDays(5) : null,
                    'grace_ends_at' => $status === SubscriptionStatus::Grace ? now()->addDays(3) : null,
                    'cancelled_by' => $status === SubscriptionStatus::Cancelled ? 'user' : null,
                    'cancelled_reason' => $status === SubscriptionStatus::Cancelled ? 'Demo cancellation.' : null,
                    'is_recurring' => ! in_array($status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired, SubscriptionStatus::Failed], true),
                ]);

                SubscriptionReceipt::factory()->create([
                    'subscription_id' => $subscription->id,
                    'type' => $index % 2 === 0 ? ReceiptType::Renewal : ReceiptType::Initial,
                ]);
            }
        }
    }

    /** @param Collection<int, User> $appUsers */
    private function seedFeedback(Collection $appUsers): void
    {
        Feedback::factory(15)->create();
        Feedback::factory(10)->fromUser()->state(fn (): array => [
            'user_id' => $appUsers->random()->id,
            'email' => $appUsers->random()->email,
        ])->create();
        Feedback::factory(8)->read()->create();
        Feedback::factory(8)->resolved()->create();
        Feedback::factory(8)->ignored()->create();
    }

    private function seedNotifications(): void
    {
        Notification::factory(8)->create();
        Notification::factory(8)->pending()->create();
        Notification::factory(8)->sent()->create();
        Notification::factory(8)->failed()->create();
    }

    private function seedLanguages(): void
    {
        collect([
            ['English', 'English', 'en', 'us', false, true, true],
            ['Urdu', 'اردو', 'ur', 'pk', true, false, true],
            ['Turkish', 'Türkçe', 'tr', 'tr', false, false, true],
            ['Arabic', 'العربية', 'ar', 'sa', true, false, false],
        ])->each(function (array $language, int $sortOrder): void {
            Language::query()->updateOrCreate(
                ['code' => $language[2]],
                [
                    'name' => $language[0],
                    'native_name' => $language[1],
                    'flag' => $language[3],
                    'is_rtl' => $language[4],
                    'is_default' => $language[5],
                    'is_active' => $language[6],
                    'sort_order' => $sortOrder,
                ],
            );
        });
    }

    /** @param Collection<int, User> $appUsers */
    private function seedPolicies(Collection $appUsers): void
    {
        $policy = Policy::query()->firstOrCreate(['key' => 'refund'], ['title' => 'Refund Policy']);
        $version = $policy->versions()->firstOrCreate(
            ['version' => '1.0'],
            [
                'content' => 'This is demo refund-policy content for exercising the policy editor.',
                'published_at' => now(),
                'is_active' => true,
            ],
        );

        $versions = PolicyVersion::query()->where('is_active', true)->get();
        $appUsers->take(40)->each(function (User $user) use ($versions): void {
            PolicyAcceptance::query()->firstOrCreate([
                'user_id' => $user->id,
                'policy_version_id' => $versions->random()->id,
            ], ['accepted_at' => now()->subDays(fake()->numberBetween(1, 120))]);
        });

        PolicyAcceptance::query()->firstOrCreate([
            'user_id' => $appUsers->first()->id,
            'policy_version_id' => $version->id,
        ], ['accepted_at' => now()]);
    }

    private function seedMailSettings(): void
    {
        $domain = EmailDomain::factory()->create(['is_default' => true]);
        EmailDomain::factory(2)->create();

        EmailSender::query()->each(function (EmailSender $sender) use ($domain): void {
            $sender->update(['email_domain_id' => $domain->id]);
        });

        SmtpSetting::query()->firstOrCreate([
            'host' => 'smtp.demo.test',
        ], [
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'demo-user',
            'password' => 'demo-password',
            'from_address' => 'noreply@demo.test',
            'from_name' => config('app.name'),
        ]);
    }

    private function seedActivity(User $staff, User $user, Ticket $ticket): void
    {
        foreach (range(1, 30) as $index) {
            ActivityLogger::log(
                $index % 2 === 0 ? ActivityModule::Ticket : ActivityModule::User,
                $index % 3 === 0 ? ActivityAction::Updated : ActivityAction::Created,
                $index % 2 === 0 ? $ticket : $user,
                ['seeded' => true],
                $staff,
                ActivityContext::Console,
            );
        }
    }
}
