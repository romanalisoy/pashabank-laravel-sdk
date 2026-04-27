<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Support;

use Romanalisoy\PashaBank\Exceptions\ValidationException;

/**
 * Amount helpers. The bank expects a decimal amount string with two fraction
 * digits (e.g. "19.80"). Internally everything is kept in minor units as a
 * BIGINT-safe integer to avoid floating-point surprises.
 */
final class Amount
{
    /** PDF spec, every "amount" field: max 12 chars. */
    public const MAX_DECIMAL_LENGTH = 12;

    /**
     * Convert an arbitrary decimal or integer-minor input into minor units.
     * - 19.80 (float)  -> 1980
     * - "19.80" (str)  -> 1980
     * - 1980 (int)     -> 1980  (already minor units)
     *
     * Integers are interpreted as minor units to avoid ambiguity when
     * developers pre-convert on their side.
     */
    public static function toMinor(int|float|string $amount): int
    {
        if (is_int($amount)) {
            return self::guardNonNegative($amount);
        }

        if (is_string($amount)) {
            if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
                throw ValidationException::invalidAmount($amount);
            }
            $amount = (float) $amount;
        }

        // Round half up, then cast.
        $minor = (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);

        return self::guardNonNegative($minor);
    }

    /**
     * Format the amount as the bank expects it on /MerchantHandler:
     * integer minor units, no decimal separator. The PDF wording "Sum of
     * transaction. Maximum length 12 symbols" is ambiguous, but the MPI
     * spec (chapter 5.1) clarifies "payment amount in minimum currency
     * units" — and the production API rejects decimal-formatted amounts
     * with `error: wrong amount`.
     *
     * 1980 minor → "1980"  (19.80 AZN)
     * 300 minor  → "300"   (3.00 AZN)
     */
    public static function toBankString(int $minor): string
    {
        $minor = self::guardNonNegative($minor);
        $string = (string) $minor;

        if (strlen($string) > self::MAX_DECIMAL_LENGTH) {
            throw ValidationException::amountTooLarge($string);
        }

        return $string;
    }

    /**
     * Human-friendly decimal representation (float) of minor units. Used for
     * DTO accessors where float arithmetic is fine (display only).
     */
    public static function toDecimal(int $minor): float
    {
        return round(self::guardNonNegative($minor) / 100, 2);
    }

    private static function guardNonNegative(int $minor): int
    {
        if ($minor < 0) {
            throw ValidationException::invalidAmount((string) $minor);
        }

        return $minor;
    }
}
