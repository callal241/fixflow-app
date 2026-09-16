<?php

namespace App\Payments\Providers;

use App\Enums\TransactionMethod;
use App\Payments\PaymentProvider;
use App\Payments\PaymentResult;

/**
 * The default provider: cash and card taken at the shop's own counter,
 * readers, and terminals.
 *
 * It performs no software hand-off to a gateway — the operator completes the
 * capture on their existing hardware (a card terminal, a swipe reader, or a
 * cash drawer) and FixFlow records the result. This is genuinely usable
 * out-of-the-box with zero configuration, and it is the honest representation
 * of how most repair shops take payments today.
 *
 * It also serves as the reference implementation for how a gateway provider
 * should behave: always report success with a stable reference, and never
 * throw for a routine decline.
 */
final class CounterTerminalProvider implements PaymentProvider
{
    public function id(): string
    {
        return 'counter';
    }

    public function name(): string
    {
        return 'Counter / card terminal';
    }

    public function supports(TransactionMethod $method): bool
    {
        // Every method is taken at the counter — cash, card, or an online
        // payment the customer completes themselves.
        return true;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function charge(float $amount, TransactionMethod $method, ?string $currency = null, array $context = []): PaymentResult
    {
        if ($amount <= 0) {
            return PaymentResult::failure($method, 'Amount must be greater than zero.');
        }

        // A stable, human-checkable reference ties the recorded transaction
        // back to the capture that happened at the counter.
        $reference = sprintf(
            'CT-%s-%s',
            strtoupper($method->value),
            substr(str_replace('.', '', number_format($amount, 2, '', '')), -6),
        );

        return PaymentResult::success($method, $reference);
    }
}
