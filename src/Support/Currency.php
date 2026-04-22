<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Support;

use Romanalisoy\PashaBank\Exceptions\ValidationException;

final class Currency
{
    /**
     * ISO-4217 currencies supported by the PASHA Bank ECOMM module, mapping
     * alpha code to numeric code. Only currencies commonly used for the
     * Azerbaijan acquiring setup are listed; extend via addMapping() if you
     * work with exotic currencies.
     *
     * @var array<string, string>
     */
    private static array $alphaToNumeric = [
        'AZN' => '944',
        'USD' => '840',
        'EUR' => '978',
        'GBP' => '826',
        'RUB' => '643',
        'TRY' => '949',
    ];

    /**
     * Resolve any acceptable currency input into the numeric ISO-4217 code
     * expected by the bank. Accepts the alpha code ("AZN") or the numeric
     * code as string ("944") or integer (944).
     */
    public static function toNumeric(string|int $currency): string
    {
        if (is_int($currency)) {
            $currency = (string) $currency;
        }

        $currency = strtoupper(trim($currency));

        if (isset(self::$alphaToNumeric[$currency])) {
            return self::$alphaToNumeric[$currency];
        }

        if (preg_match('/^\d{3}$/', $currency) === 1 && in_array($currency, self::$alphaToNumeric, true)) {
            return $currency;
        }

        throw ValidationException::invalidCurrency($currency);
    }

    /**
     * Convert a numeric ISO-4217 code back to its alpha counterpart. Useful
     * when hydrating DTOs from bank responses.
     */
    public static function toAlpha(string $numeric): string
    {
        $alpha = array_search($numeric, self::$alphaToNumeric, true);

        if ($alpha === false) {
            throw ValidationException::invalidCurrency($numeric);
        }

        return $alpha;
    }

    /**
     * Register a currency mapping not present in the default table.
     */
    public static function addMapping(string $alpha, string $numeric): void
    {
        self::$alphaToNumeric[strtoupper($alpha)] = $numeric;
    }
}
