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
     * Format minor units as the decimal string expected by the bank. Always
     * two fraction digits, no thousands separators.
     */
    public static function toBankString(int $minor): string
    {
        $minor = self::guardNonNegative($minor);
        $decimal = number_format($minor / 100, 2, '.', '');

        if (strlen($decimal) > self::MAX_DECIMAL_LENGTH) {
            throw ValidationException::amountTooLarge($decimal);
        }

        return $decimal;
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
