<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations;

use Romanalisoy\PashaBank\Data\EndOfDayResult;

/**
 * End of business day — command=b. Reconciles the merchant's daily
 * volume. PDF §4.20. Usually wired to a nightly schedule.
 */
class CloseDay extends Operation
{
    public function execute(): EndOfDayResult
    {
        $response = $this->send(array_merge($this->parameters(), [
            'command' => 'b',
        ]));

        return EndOfDayResult::fromResponse($response);
    }
}
