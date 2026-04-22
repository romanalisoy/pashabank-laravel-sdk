<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Romanalisoy\PashaBank\Data\TransactionStatus;

/**
 * Dispatched when the bank reports a declined / failed / timed-out
 * transaction at completion time. The status DTO carries the RESULT_CODE
 * you can surface to the customer.
 */
final class PaymentFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $transactionId,
        public readonly TransactionStatus $status,
        public readonly ?Model $transaction = null,
    ) {}
}
