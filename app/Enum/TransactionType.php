<?php

namespace App\Enum;

use App\Models\SubscriptionTransaction;

/**
 * The kind of money movement a {@see SubscriptionTransaction} records — the
 * provider-agnostic vocabulary every payment integration translates its own
 * events into. Closed vocabulary — adding a new type is a code change.
 *
 * Only events where money actually moved belong here. Lifecycle changes with
 * no money attached (auto-renew toggled, billing retry failed, expired,
 * revoked from Family Sharing) live on the subscription row and in the audit
 * log, not in the financial ledger.
 *
 * `amount_minor` is always a non-negative magnitude; the type alone decides
 * which way the money went ({@see self::direction()}). Gross sales are the
 * charges; net sales are charges − refunds + reversed refunds.
 */
enum TransactionType: string
{
    /** First charge of a contract (a zero-value free-trial start counts — it opens the contract). */
    case Initial = 'initial';

    /** A recurring charge that extended an existing contract. */
    case Renewal = 'renewal';

    /** The charge that opened a new plan on an existing contract (upgrade/downgrade/crossgrade). */
    case PlanChange = 'plan_change';

    /** Money returned to the customer for an earlier charge (possibly partial). */
    case Refund = 'refund';

    /** A refund the provider took back — the money is ours again. */
    case RefundReversed = 'refund_reversed';

    public function label(): string
    {
        return __("enums.transaction_type.{$this->name}");
    }

    /** +1 when money came in, −1 when it went back to the customer. */
    public function direction(): int
    {
        return $this === self::Refund ? -1 : 1;
    }

    public function isCharge(): bool
    {
        return in_array($this, self::charges(), true);
    }

    /** @return list<self> types that are a customer being charged — what "gross sales" sums */
    public static function charges(): array
    {
        return [self::Initial, self::Renewal, self::PlanChange];
    }

    /** @return list<string> */
    public static function chargeValues(): array
    {
        return array_map(fn (self $type): string => $type->value, self::charges());
    }
}
