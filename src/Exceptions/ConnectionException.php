<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Exceptions;

use Throwable;

/**
 * Thrown when the SDK cannot reach the bank: TLS handshake failure, DNS,
 * timeout, socket error. Retry-safe — the payment was never registered.
 */
final class ConnectionException extends PashaBankException
{
    public static function fromTransport(string $message, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Could not reach PASHA Bank ECOMM module: %s', $message),
            0,
            $previous
        );
    }
}
