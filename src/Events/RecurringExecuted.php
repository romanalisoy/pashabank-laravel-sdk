<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Romanalisoy\PashaBank\Data\OperationResult;

/**
 * Dispatched after a recurring charge (command=e) has been processed —
 * whether successful or declined. Check $result->isSuccessful() to know
 * which. This is the single hook for subscription-billing integrations.
 */
final class RecurringExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly string $billerClientId,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly OperationResult $result,
        public readonly ?Model $recurring = null,
    ) {}
}
