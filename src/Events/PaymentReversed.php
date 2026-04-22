<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Romanalisoy\PashaBank\Data\OperationResult;

/**
 * Dispatched after a successful reversal (command=r). Partial reversals
 * set the remaining balance on the original transaction; listen here if
 * you track ledgers outside the SDK's own persistence.
 */
final class PaymentReversed
{
    use Dispatchable;

    public function __construct(
        public readonly string $transactionId,
        public readonly int $amountMinor,
        public readonly OperationResult $result,
        public readonly ?Model $transaction = null,
    ) {}
}
