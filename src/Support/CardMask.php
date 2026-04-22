<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Support;

/**
 * Helpers for masking sensitive card data before it enters logs, events, or
 * any persistence layer the developer may set up. The SDK never stores PAN
 * in full; this class exists for defence in depth.
 */
final class CardMask
{
    /**
     * Mask a primary account number to first 6 + last 4 digits. If the
     * string doesn't look like a PAN (non-numeric, too short), it is
     * returned unchanged so real error messages are not destroyed.
     */
    public static function mask(?string $pan): ?string
    {
        if ($pan === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $pan) ?? '';

        if (strlen($digits) < 13 || strlen($digits) > 19) {
            return $pan;
        }

        $bin = substr($digits, 0, 6);
        $last4 = substr($digits, -4);
        $middleLength = strlen($digits) - 10;

        return $bin.str_repeat('*', $middleLength).$last4;
    }

    /**
     * Recursively scrub known sensitive keys from an associative array.
     * Useful before logging a request/response payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function scrub(array $payload): array
    {
        $sensitive = ['pan', 'cvv2', 'cvc2', 'expiry', 'cardname', 'pan2'];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::scrub($value);

                continue;
            }

            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $payload[$key] = $key === 'pan' || $key === 'pan2'
                    ? self::mask((string) $value)
                    : '***';
            }
        }

        return $payload;
    }
}
