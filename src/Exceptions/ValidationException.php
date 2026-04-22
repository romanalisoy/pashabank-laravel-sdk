<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Exceptions;

use InvalidArgumentException;

/**
 * Thrown for client-side validation failures (bad amount, unknown currency,
 * missing required field). This is a subclass of PashaBankException so
 * catch-all handlers still work, but also extends InvalidArgumentException
 * for conventional "bad input" handling.
 */
final class ValidationException extends PashaBankException
{
    public static function invalidAmount(string $value): self
    {
        return new self(sprintf(
            'Invalid amount "%s". Expected a non-negative decimal with up to 2 fraction digits, or minor-unit integer.',
            $value
        ));
    }

    public static function amountTooLarge(string $decimal): self
    {
        return new self(sprintf(
            'Amount "%s" exceeds the 12-character limit enforced by the bank.',
            $decimal
        ));
    }

    public static function invalidCurrency(string|int $value): self
    {
        return new self(sprintf(
            'Unknown currency "%s". Register it with Currency::addMapping() or use one of the built-in codes.',
            (string) $value
        ));
    }

    public static function missingField(string $field): self
    {
        return new self(sprintf('Required field "%s" was not set.', $field));
    }

    public static function descriptionTooLong(int $length): self
    {
        return new self(sprintf(
            'Description is %d characters long; the bank accepts at most 125.',
            $length
        ));
    }

    public static function billerClientIdTooLong(int $length): self
    {
        return new self(sprintf(
            'biller_client_id is %d characters long; the bank accepts at most 28.',
            $length
        ));
    }

    public static function invalidExpiry(string $value): self
    {
        return new self(sprintf(
            'Invalid expiry "%s". Expected MMYY format (e.g. "1231").',
            $value
        ));
    }

    public static function invalidLanguage(string $value): self
    {
        return new self(sprintf(
            'Invalid language "%s". Use a 2-letter code (az, en, ru).',
            $value
        ));
    }

    public static function invalidIpAddress(string $value): self
    {
        return new self(sprintf('Invalid client IP address "%s".', $value));
    }
}
