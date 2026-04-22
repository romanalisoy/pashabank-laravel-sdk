<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Romanalisoy\PashaBank\Data\OperationResult;

final class RecurringDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $billerClientId,
        public readonly OperationResult $result,
        public readonly ?Model $recurring = null,
    ) {}
}
