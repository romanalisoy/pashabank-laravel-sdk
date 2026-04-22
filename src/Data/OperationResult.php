<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Data;

use Romanalisoy\PashaBank\Client\Response;
use Romanalisoy\PashaBank\Support\ResultCode;

/**
 * Uniform result for one-shot commands that return RESULT + RESULT_CODE:
 * reversal (-r), refund (-k), DMS completion (-t), recurring execution
 * (-e), recurring deletion (-x), and close day (-b). Specialised DTOs
 * extend this where extra fields are returned.
 */
class OperationResult
{
    /**
     * @param  array<string, string>  $raw
     */
    public function __construct(
        public readonly string $result,
        public readonly ?string $resultCode,
        public readonly ?string $rrn = null,
        public readonly ?string $approvalCode = null,
        public readonly ?string $refundTransactionId = null,
        public readonly array $raw = [],
    ) {}

    public static function fromResponse(Response $response): static
    {
        /** @phpstan-ignore-next-line new.static */
        return new static(
            result: $response->get('RESULT') ?? 'UNKNOWN',
            resultCode: $response->get('RESULT_CODE'),
            rrn: $response->get('RRN'),
            approvalCode: $response->get('APPROVAL_CODE'),
            refundTransactionId: $response->get('REFUND_TRANS_ID'),
            raw: $response->all(),
        );
    }

    public function isSuccessful(): bool
    {
        if ($this->result !== 'OK') {
            return false;
        }

        return ResultCode::isApproved($this->resultCode)
            || ResultCode::isReversalAccepted($this->resultCode);
    }

    public function description(): string
    {
        return ResultCode::describe($this->resultCode);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'result' => $this->result,
            'result_code' => $this->resultCode,
            'description' => $this->description(),
            'rrn' => $this->rrn,
            'approval_code' => $this->approvalCode,
            'refund_transaction_id' => $this->refundTransactionId,
        ];
    }
}
