<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Romanalisoy\PashaBank\Data\OperationResult;

/**
 * Dispatched after a successful refund (command=k). The bank returns a
 * REFUND_TRANS_ID; the DTO carries it for later reversal if needed.
 */
final class PaymentRefunded
{
    use Dispatchable;

    public function __construct(
        public readonly string $transactionId,
        public readonly int $amountMinor,
        public readonly OperationResult $result,
        public readonly ?Model $transaction = null,
    ) {}
}
