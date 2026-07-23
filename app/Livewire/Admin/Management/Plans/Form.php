<?php

namespace App\Livewire\Admin\Management\Plans;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\BillingInterval;
use App\Enum\PaymentProvider;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\PlanPriceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    use LogsAdminActivity;

    public ?int $planId = null;

    #[Validate]
    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public bool $is_best_deal = false;

    public int $sort_order = 0;

    /** @var array<string, mixed> keyed by config('panel.features') key */
    public array $features = [];

    /**
     * @var array<int, array{id: ?int, amount: string, compare_at_amount: string,
     *     currency: string, billing_period: int, billing_interval: string,
     *     trial_period: int, trial_interval: string, grace_period: int,
     *     grace_interval: string, is_active: bool,
     *     providers: array<int, array{id: ?int, provider: string, external_id: string, is_active: bool}>}>
     */
    public array $prices = [];

    protected function indexRoute(): string
    {
        return 'admin.plans.index';
    }

    public function mount(?Plan $plan = null): void
    {
        if ($plan) {
            $this->isEditing = true;
            $this->planId = $plan->id;
            $this->name = $plan->name;
            $this->description = $plan->description ?? '';
            $this->is_active = $plan->is_active;
            $this->is_best_deal = $plan->is_best_deal;
            $this->sort_order = $plan->sort_order;
            $this->features = $plan->features();

            $this->prices = $plan->prices()->with('providers')->orderBy('amount')->get()
                ->map(fn (PlanPrice $price): array => [
                    'id' => $price->id,
                    'amount' => (string) $price->amount,
                    'compare_at_amount' => $price->compare_at_amount !== null ? (string) $price->compare_at_amount : '',
                    'currency' => $price->currency,
                    'billing_period' => $price->billing_period,
                    'billing_interval' => $price->billing_interval->value,
                    'trial_period' => $price->trial_period,
                    'trial_interval' => $price->trial_interval->value,
                    'grace_period' => $price->grace_period,
                    'grace_interval' => $price->grace_interval->value,
                    'is_active' => $price->is_active,
                    'providers' => $price->providers->map(fn (PlanPriceProvider $provider): array => [
                        'id' => $provider->id,
                        'provider' => $provider->provider->value,
                        'external_id' => $provider->external_id,
                        'is_active' => $provider->is_active,
                    ])->all(),
                ])->all();
        } else {
            $this->features = collect(config('panel.features', []))
                ->map(fn (array $feature): mixed => $feature['default'] ?? null)
                ->all();

            $this->prices = [$this->emptyPrice()];
        }
    }

    protected function rules(): array
    {
        $intervals = array_map(fn (BillingInterval $c): string => $c->value, BillingInterval::cases());
        $providers = array_map(fn (PaymentProvider $c): string => $c->value, PaymentProvider::cases());

        $rules = [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'is_best_deal' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],

            'prices' => ['array'],
            'prices.*.amount' => ['required', 'numeric', 'min:0'],
            'prices.*.compare_at_amount' => ['nullable', 'numeric', 'min:0'],
            'prices.*.currency' => ['required', 'string', 'size:3'],
            'prices.*.billing_period' => ['required', 'integer', 'min:1'],
            'prices.*.billing_interval' => ['required', Rule::in($intervals)],
            'prices.*.trial_period' => ['nullable', 'integer', 'min:0'],
            'prices.*.trial_interval' => ['required', Rule::in($intervals)],
            'prices.*.grace_period' => ['nullable', 'integer', 'min:0'],
            'prices.*.grace_interval' => ['required', Rule::in($intervals)],
            'prices.*.is_active' => ['boolean'],

            'prices.*.providers' => ['array'],
            'prices.*.providers.*.provider' => ['required', Rule::in($providers)],
            'prices.*.providers.*.external_id' => ['required', 'string', 'max:255'],
            'prices.*.providers.*.is_active' => ['boolean'],
        ];

        foreach (config('panel.features', []) as $key => $feature) {
            $rules["features.{$key}"] = match ($feature['type'] ?? 'string') {
                'integer' => ['nullable', 'integer', 'min:0'],
                'boolean' => ['boolean'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'prices.*.amount' => 'amount',
            'prices.*.compare_at_amount' => 'compare-at amount',
            'prices.*.currency' => 'currency',
            'prices.*.billing_period' => 'billing period',
            'prices.*.billing_interval' => 'billing interval',
            'prices.*.trial_period' => 'trial period',
            'prices.*.trial_interval' => 'trial interval',
            'prices.*.grace_period' => 'grace period',
            'prices.*.grace_interval' => 'grace interval',
            'prices.*.providers.*.provider' => 'provider',
            'prices.*.providers.*.external_id' => 'external ID',
        ];
    }

    // ── Prices repeater ──────────────────────────────────────────────────────

    protected function emptyPrice(): array
    {
        return [
            'id' => null,
            'amount' => '',
            'compare_at_amount' => '',
            'currency' => 'USD',
            'billing_period' => 1,
            'billing_interval' => BillingInterval::Month->value,
            'trial_period' => 0,
            'trial_interval' => BillingInterval::Day->value,
            'grace_period' => 0,
            'grace_interval' => BillingInterval::Day->value,
            'is_active' => true,
            'providers' => [],
        ];
    }

    protected function emptyProvider(): array
    {
        return [
            'id' => null,
            'provider' => PaymentProvider::Stripe->value,
            'external_id' => '',
            'is_active' => true,
        ];
    }

    public function addPrice(): void
    {
        $this->prices[] = $this->emptyPrice();
    }

    public function removePrice(int $index): void
    {
        $price = $this->prices[$index] ?? null;

        if (! $price) {
            return;
        }

        if (! empty($price['id']) && PlanPrice::whereKey($price['id'])->whereHas('subscriptions')->exists()) {
            $this->toastError('This price has subscriptions and cannot be removed.', 'Deactivate it instead.');

            return;
        }

        unset($this->prices[$index]);
        $this->prices = array_values($this->prices);
    }

    public function addProvider(int $priceIndex): void
    {
        $this->prices[$priceIndex]['providers'][] = $this->emptyProvider();
    }

    public function removeProvider(int $priceIndex, int $providerIndex): void
    {
        unset($this->prices[$priceIndex]['providers'][$providerIndex]);
        $this->prices[$priceIndex]['providers'] = array_values($this->prices[$priceIndex]['providers']);
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    protected function syncPrices(Plan $plan, array $removedPriceIds): void
    {
        PlanPrice::whereIn('id', $removedPriceIds)->delete();

        foreach ($this->prices as $priceData) {
            $price = ! empty($priceData['id'])
                ? PlanPrice::findOrFail($priceData['id'])
                : new PlanPrice;

            $price->plan_id = $plan->id;
            $price->fill([
                'amount' => $priceData['amount'],
                'compare_at_amount' => $priceData['compare_at_amount'] !== '' ? $priceData['compare_at_amount'] : null,
                'currency' => strtoupper($priceData['currency']),
                'billing_period' => $priceData['billing_period'],
                'billing_interval' => $priceData['billing_interval'],
                'trial_period' => $priceData['trial_period'] ?: 0,
                'trial_interval' => $priceData['trial_interval'],
                'grace_period' => $priceData['grace_period'] ?: 0,
                'grace_interval' => $priceData['grace_interval'],
                'is_active' => (bool) ($priceData['is_active'] ?? true),
            ]);
            $price->save();

            $this->syncProviders($price, $priceData['providers'] ?? []);
        }
    }

    protected function syncProviders(PlanPrice $price, array $providersData): void
    {
        $existingIds = $price->providers()->pluck('id')->all();
        $submittedIds = collect($providersData)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();

        PlanPriceProvider::whereIn('id', array_diff($existingIds, $submittedIds))->delete();

        foreach ($providersData as $providerData) {
            $provider = ! empty($providerData['id'])
                ? PlanPriceProvider::findOrFail($providerData['id'])
                : new PlanPriceProvider;

            $provider->plan_price_id = $price->id;
            $provider->fill([
                'provider' => $providerData['provider'],
                'external_id' => $providerData['external_id'],
                'is_active' => (bool) ($providerData['is_active'] ?? true),
            ]);
            $provider->save();
        }
    }

    public function save()
    {
        $this->validate();

        $removedPriceIds = [];

        if ($this->isEditing) {
            $existingIds = PlanPrice::where('plan_id', $this->planId)->pluck('id')->all();
            $submittedIds = collect($this->prices)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();
            $removedPriceIds = array_diff($existingIds, $submittedIds);

            if ($removedPriceIds !== [] && PlanPrice::whereIn('id', $removedPriceIds)->whereHas('subscriptions')->exists()) {
                $this->toastError('Cannot save — a removed price already has subscriptions.', 'Deactivate it instead of removing it.');

                return;
            }
        }

        $plan = $this->isEditing ? Plan::findOrFail($this->planId) : new Plan;
        $before = $this->isEditing ? $plan->getOriginal() : null;

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'is_best_deal' => $this->is_best_deal,
            'sort_order' => $this->sort_order,
        ];

        DB::transaction(function () use ($plan, $data, $removedPriceIds): void {
            $plan->fill($data);
            $plan->save();

            $this->syncPrices($plan, $removedPriceIds);
        });

        if ($this->isEditing) {
            $changes = $this->auditDiff($plan, $before);
            if ($changes !== []) {
                $this->logActivity(ActivityModule::Plan, ActivityAction::Updated, $plan, $changes);
            }
        } else {
            $this->logActivity(ActivityModule::Plan, ActivityAction::Created, $plan, [
                'attributes' => ['name' => $plan->name, 'slug' => $plan->slug],
            ]);
        }

        return $this->redirectWithSuccess(
            "{$plan->name} plan ".($this->isEditing ? 'updated' : 'created').' successfully.',
        );
    }

    public function render(): View
    {
        return view('livewire.admin.management.plans.form', [
            'billingIntervalOptions' => collect(BillingInterval::cases())->mapWithKeys(fn (BillingInterval $c): array => [$c->value => $c->label()])->all(),
            'paymentProviderOptions' => collect(PaymentProvider::cases())->mapWithKeys(fn (PaymentProvider $c): array => [$c->value => $c->label()])->all(),
        ]);
    }
}
