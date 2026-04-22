<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Support;

/**
 * Human-readable descriptions for the RESULT_CODE field returned by
 * CardSuite. Matches chapter 8 "RESULT_CODE" of the integration PDF.
 */
final class ResultCode
{
    public const APPROVED = '000';

    public const REVERSAL_ACCEPTED = '400';

    public const BUSINESS_DAY_IN_BALANCE = '500';

    public const BUSINESS_DAY_OUT_OF_BALANCE = '501';

    public const ISSUER_INOPERATIVE = '907';

    public const ROUTING_NOT_FOUND = '908';

    public const SYSTEM_MALFUNCTION = '909';

    public const ISSUER_TIMEOUT = '911';

    public const ISSUER_UNAVAILABLE = '912';

    public const REVERSAL_ORIGINAL_NOT_FOUND = '914';

    /** @var array<int|string, string> */
    private static array $descriptions = [
        '000' => 'Approved',
        '100' => 'Decline, general, no comments',
        '101' => 'Decline, expired card',
        '102' => 'Decline, suspected fraud',
        '103' => 'Decline, card acceptor contact acquirer',
        '107' => 'Decline, refer to card issuer',
        '108' => 'Decline, destination of route not found',
        '110' => 'Decline, invalid amount',
        '111' => 'Decline, invalid card number',
        '115' => 'Decline, requested function not supported',
        '116' => 'Decline, no sufficient funds',
        '118' => 'Decline, no card record',
        '119' => 'Decline, transaction not permitted to cardholder',
        '120' => 'Decline, transaction not permitted to terminal',
        '122' => 'Decline, security violation',
        '125' => 'Decline, card not effective',
        '129' => 'Decline, suspected counterfeit card',
        '400' => 'Accepted (for reversal)',
        '500' => 'Status message, reconciled, in balance',
        '501' => 'Status message, reconciled, out of balance',
        '907' => 'Decline, card issuer or switch inoperative',
        '908' => 'Decline, transaction destination cannot be found for routing',
        '909' => 'Decline, system malfunction',
        '911' => 'Decline, card issuer timed out',
        '912' => 'Decline, card issuer unavailable',
        '914' => 'Decline, reversal original not found',
    ];

    public static function describe(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'Unknown';
        }

        return self::$descriptions[$code] ?? sprintf('Unknown response code (%s)', $code);
    }

    public static function isApproved(?string $code): bool
    {
        return $code === self::APPROVED;
    }

    public static function isReversalAccepted(?string $code): bool
    {
        return $code === self::REVERSAL_ACCEPTED;
    }
}
