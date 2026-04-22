<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Data;

use Romanalisoy\PashaBank\Client\Response;
use Romanalisoy\PashaBank\Support\ResultCode;

/**
 * The authoritative status of a transaction as reported by the bank's
 * /MerchantHandler?command=c call. All fields mirror the PDF's
 * "Transaction Result" section; nullable where the bank may legitimately
 * omit them (e.g. APPROVAL_CODE on failed transactions).
 */
final readonly class TransactionStatus
{
    /**
     * @param  array<string, string>  $raw  Entire KEY: VALUE payload for debugging.
     */
    public function __construct(
        public string $result,
        public ?string $resultCode,
        public ?string $resultPs,
        public ?string $threeDSecure,
        public ?string $rrn,
        public ?string $approvalCode,
        public ?string $cardMask,
        public ?string $recurringPaymentId,
        public ?string $recurringPaymentExpiry,
        public ?string $resultCode2,
        public ?string $rrn2,
        public ?string $approvalCode2,
        public ?string $cardMask2,
        public array $raw,
    ) {}

    public static function fromResponse(Response $response): self
    {
        return new self(
            result: $response->get('RESULT') ?? 'UNKNOWN',
            resultCode: $response->get('RESULT_CODE'),
            resultPs: $response->get('RESULT_PS'),
            threeDSecure: $response->get('3DSECURE'),
            rrn: $response->get('RRN'),
            approvalCode: $response->get('APPROVAL_CODE'),
            cardMask: $response->get('CARD_NUMBER'),
            recurringPaymentId: $response->get('RECC_PMNT_ID'),
            recurringPaymentExpiry: $response->get('RECC_PMNT_EXPIRY'),
            resultCode2: $response->get('RESULT_CODE2'),
            rrn2: $response->get('RRN2'),
            approvalCode2: $response->get('APPROVAL_CODE2'),
            cardMask2: $response->get('CARD_NUMBER2'),
            raw: $response->all(),
        );
    }

    public function isSuccessful(): bool
    {
        return $this->result === 'OK' && ResultCode::isApproved($this->resultCode);
    }

    public function isFailed(): bool
    {
        return in_array($this->result, ['FAILED', 'DECLINED'], true);
    }

    public function isPending(): bool
    {
        return in_array($this->result, ['PENDING', 'CREATED'], true);
    }

    public function isReversed(): bool
    {
        return in_array($this->result, ['REVERSED', 'AUTOREVERSED'], true);
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
            'three_d_secure' => $this->threeDSecure,
            'rrn' => $this->rrn,
            'approval_code' => $this->approvalCode,
            'card_mask' => $this->cardMask,
            'recurring_payment_id' => $this->recurringPaymentId,
            'recurring_payment_expiry' => $this->recurringPaymentExpiry,
        ];
    }
}
