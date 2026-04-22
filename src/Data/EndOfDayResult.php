<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Data;

use Romanalisoy\PashaBank\Client\Response;
use Romanalisoy\PashaBank\Support\ResultCode;

/**
 * Response payload for the close-day (command=b) call. The reconciliation
 * counters are only populated when result_code begins with "5" (reconciled
 * in/out of balance); otherwise the totals stay null.
 */
final readonly class EndOfDayResult
{
    public function __construct(
        public string $result,
        public ?string $resultCode,
        public ?int $creditCount,
        public ?int $creditReversalCount,
        public ?int $debitCount,
        public ?int $debitReversalCount,
        public ?string $creditSum,
        public ?string $creditReversalSum,
        public ?string $debitSum,
        public ?string $debitReversalSum,
        /** @var array<string, string> */
        public array $raw,
    ) {}

    public static function fromResponse(Response $response): self
    {
        return new self(
            result: $response->get('RESULT') ?? 'UNKNOWN',
            resultCode: $response->get('RESULT_CODE'),
            creditCount: self::int($response, 'FLD_074'),
            creditReversalCount: self::int($response, 'FLD_075'),
            debitCount: self::int($response, 'FLD_076'),
            debitReversalCount: self::int($response, 'FLD_077'),
            creditSum: $response->get('FLD_086'),
            creditReversalSum: $response->get('FLD_087'),
            debitSum: $response->get('FLD_088'),
            debitReversalSum: $response->get('FLD_089'),
            raw: $response->all(),
        );
    }

    public function isSuccessful(): bool
    {
        return $this->result === 'OK';
    }

    public function isReconciled(): bool
    {
        return $this->resultCode === ResultCode::BUSINESS_DAY_IN_BALANCE;
    }

    private static function int(Response $response, string $key): ?int
    {
        $value = $response->get($key);

        return $value === null ? null : (int) $value;
    }
}
