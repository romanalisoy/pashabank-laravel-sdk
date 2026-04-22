<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a recurring template has been registered at the bank.
 * The biller_client_id is the stable identifier the developer chose; keep
 * it on your subscription / customer record so future ->execute() calls
 * can reach this template.
 */
final class RecurringRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly string $billerClientId,
        public readonly string $expiry,
        public readonly ?Model $recurring = null,
    ) {}
}
