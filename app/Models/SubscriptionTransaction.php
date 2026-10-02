<?php

namespace App\Models;

use App\Contracts\ProviderNotification;
use App\Enum\PaymentProvider;
use App\Enum\TransactionType;
use App\Support\Money\Money;
use App\Support\WebhookNotificationRegistry;
use Database\Factories\SubscriptionTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One real money movement against a {@see Subscription} — a charge, a
 * renewal, a refund — exactly as the provider reported it. The subscription
 * is the entitlement; this is the financial record. A subscription may have
 * none (an admin or promotional grant).
 *
 * Money is `amount_minor` (integer, the currency's minor unit — see
 * {@see Money}) + `currency`, already normalised from the provider's own unit
 * at the integration boundary. It is the gross amount the customer was
 * charged (tax-inclusive, before store commission) — not proceeds. The
 * amount is always non-negative; {@see TransactionType::direction()} says
 * which way it went. Both are null only when the provider sent no price.
 *
 * `purchased_at` is when the money moved: the charge time for a charge, the
 * refund time for a refund. `periods_covered` is how many billing periods a
 * charge pays for (a pay-up-front offer covers several). Revenue is dated by it, never by `created_at`
 * (when we happened to ingest it).
 *
 * Never derived from `plan_prices` — a later price change must not rewrite
 * history.
 *
 * Three kinds of identifier live here and mean different things:
 *   - `provider_transaction_id` — the provider's id for this one charge/refund
 *     (Apple `transactionId`); the idempotency key for writing ledger rows;
 *   - `provider_original_id` — the provider's id for the whole contract
 *     (Apple `originalTransactionId`); how a contract is found again;
 *   - `notification_provider` + `notification_id` — the internal row id in
 *     that provider's raw notification table (`apple_notifications.id`) this
 *     row was recorded from. Apple's own `notificationUUID` stays on that
 *     table (`apple_notifications.notification_uuid`).
 *
 * `idempotency_key` names the money movement itself and is unique per
 * provider at the database level — `charge:{transactionId}` (one charge per
 * provider transaction, whatever its type), `refund:{transactionId}:{event}`
 * (each distinct refund event on a transaction), `reversal:{refund key}` (at
 * most one reversal per refund). A row written with no key (manual entry,
 * seed) gets a unique `manual:{ulid}`. `related_transaction_id` points a
 * refund at the charge it refunds and a reversal at the refund it reverses.
 */
#[Fillable([
    'subscription_id',
    'provider',
    'type',
    'amount_minor',
    'currency',
    'purchased_at',
    'periods_covered',
    'provider_transaction_id',
    'provider_original_id',
    'idempotency_key',
    'related_transaction_id',
    'payload',
    'notification_provider',
    'notification_id',
])]
class SubscriptionTransaction extends Model
{
    /** @use HasFactory<SubscriptionTransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->idempotency_key ??= 'manual:'.Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'type' => TransactionType::class,
            'amount_minor' => 'integer',
            'purchased_at' => 'datetime',
            'periods_covered' => 'integer',
            'payload' => 'array',
            'notification_provider' => PaymentProvider::class,
        ];
    }

    /**
     * Get the subscription this transaction belongs to.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The charge a refund refunds, or the refund a reversal reverses.
     *
     * @return BelongsTo<SubscriptionTransaction, $this>
     */
    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    /**
     * Reversals of this refund (at most one, enforced by its idempotency key).
     *
     * @return HasMany<SubscriptionTransaction, $this>
     */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'related_transaction_id')->where('type', TransactionType::RefundReversed);
    }

    /** The transaction's amount, or null when the provider reported none. */
    public function money(): ?Money
    {
        return $this->amount_minor === null || $this->currency === null
            ? null
            : Money::ofMinor($this->amount_minor, $this->currency);
    }

    /** The amount signed by direction — negative for a refund. */
    public function signedMoney(): ?Money
    {
        $money = $this->money();

        return $money && $this->type->direction() < 0 ? Money::ofMinor(-$money->minor, $money->currency) : $money;
    }

    /**
     * Transactions that carry an amount — the only ones money aggregates
     * should ever read.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePriced(Builder $query): Builder
    {
        return $query->whereNotNull('subscription_transactions.amount_minor')
            ->whereNotNull('subscription_transactions.currency');
    }

    /**
     * Customer charges (gross sales).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCharges(Builder $query): Builder
    {
        return $query->whereIn('subscription_transactions.type', TransactionType::chargeValues());
    }

    /**
     * SQL for the amount signed by direction (refunds negative), so a plain
     * `SUM()` over any set of transactions yields net sales.
     */
    public static function signedAmountSql(string $table = 'subscription_transactions'): string
    {
        return "CASE WHEN {$table}.type = '".TransactionType::Refund->value."' THEN -{$table}.amount_minor ELSE {$table}.amount_minor END";
    }

    /**
     * Net sales per currency over a transaction query: charges − refunds +
     * reversed refunds, each currency kept separate (never summed together).
     *
     * @param  Builder<self>  $query
     * @return array<string, Money> currency => net amount, largest first
     */
    public static function netByCurrency(Builder $query): array
    {
        return (clone $query)
            ->priced()
            ->groupBy('subscription_transactions.currency')
            ->toBase()
            ->select(['subscription_transactions.currency'])
            ->selectRaw('SUM('.self::signedAmountSql().') as net')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->currency => Money::ofMinor((int) $row->net, (string) $row->currency)])
            ->sortByDesc(fn (Money $money): int => $money->minor)
            ->all();
    }

    /**
     * The raw provider webhook notification this transaction was recorded
     * from, if linked — resolved via {@see WebhookNotificationRegistry}
     * rather than a native polymorphic relation, since `notification_provider`
     * names a provider, not a model class.
     */
    public function notification(): ?ProviderNotification
    {
        return WebhookNotificationRegistry::resolve($this->notification_provider, $this->notification_id);
    }
}
