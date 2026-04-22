<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Romanalisoy\PashaBank\Data\TransactionStatus;

/**
 * Dispatched when the callback handler (or manual completion call)
 * receives a successful status from the bank. Listen to this event to
 * mark orders paid, send receipts, trigger fulfilment.
 */
final class PaymentCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $transactionId,
        public readonly TransactionStatus $status,
        public readonly ?Model $transaction = null,
    ) {}
}
