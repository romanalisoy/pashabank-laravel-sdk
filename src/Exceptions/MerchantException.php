<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Exceptions;

/**
 * Thrown when the bank's response contains an "error" field or any other
 * payload that indicates the request was rejected before reaching the
 * authorization layer (e.g. "no transaction id", "wrong transaction id").
 *
 * Failed authorizations do NOT raise this — they return a TransactionStatus
 * with RESULT=FAILED. This exception is specifically for malformed calls or
 * structural errors the developer needs to fix.
 */
final class MerchantException extends PashaBankException
{
    /** @var array<string, string> */
    private array $rawResponse;

    /**
     * @param  array<string, string>  $rawResponse
     */
    public function __construct(string $message, array $rawResponse = [])
    {
        parent::__construct($message);
        $this->rawResponse = $rawResponse;
    }

    /** @return array<string, string> */
    public function rawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * @param  array<string, string>  $rawResponse
     */
    public static function fromErrorField(string $error, array $rawResponse): self
    {
        return new self(sprintf('PASHA Bank rejected the request: %s', $error), $rawResponse);
    }
}
