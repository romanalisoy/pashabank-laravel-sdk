<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Romanalisoy\PashaBank\Client\MerchantConfig;

/**
 * Dispatched after the bank accepts a payment registration (SMS, DMS, or
 * recurring registration) and returns a transaction identifier. The
 * customer has NOT yet entered card details at this point.
 */
final class PaymentRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly MerchantConfig $merchant,
        public readonly string $transactionId,
        public readonly string $command,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?Model $transaction = null,
    ) {}
}
