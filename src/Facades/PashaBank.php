<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Facades;

use Illuminate\Support\Facades\Facade;
use Romanalisoy\PashaBank\Operations\CloseDay;
use Romanalisoy\PashaBank\Operations\DmsAuthorization;
use Romanalisoy\PashaBank\Operations\DmsCompletion;
use Romanalisoy\PashaBank\Operations\Recurring\RecurringBuilder;
use Romanalisoy\PashaBank\Operations\Refund;
use Romanalisoy\PashaBank\Operations\Reversal;
use Romanalisoy\PashaBank\Operations\SmsPayment;
use Romanalisoy\PashaBank\Operations\TransactionResult;
use Romanalisoy\PashaBank\PashaBankManager;
use Romanalisoy\PashaBank\Testing\PashaBankFake;

/**
 * @method static PashaBankManager merchant(string $key)
 * @method static SmsPayment sms()
 * @method static DmsAuthorization dms()
 * @method static DmsCompletion dmsComplete(string $transactionId)
 * @method static Reversal reversal(string $transactionId)
 * @method static Refund refund(string $transactionId)
 * @method static TransactionResult completion(string $transactionId)
 * @method static RecurringBuilder recurring()
 * @method static CloseDay closeDay()
 * @method static string clientHandlerUrl(string $transactionId, ?string $merchant = null)
 * @method static PashaBankFake fake()
 *
 * @see PashaBankManager
 */
final class PashaBank extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PashaBankManager::class;
    }
}
