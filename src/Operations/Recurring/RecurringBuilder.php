<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations\Recurring;

use Illuminate\Contracts\Events\Dispatcher;
use Romanalisoy\PashaBank\Client\EcommClient;
use Romanalisoy\PashaBank\Client\MerchantConfig;

/**
 * Facade over the different recurring flavours. Chain one of the entry
 * methods to get the right builder for your scenario:
 *
 *     PashaBank::recurring()
 *         ->registerWithSmsFirstPayment()
 *         ->amount(9.99)->billerClientId('sub-42')->expiry('1231')
 *         ->register();
 *
 *     PashaBank::recurring()->execute('sub-42')->amount(9.99)->charge();
 *     PashaBank::recurring()->delete('sub-42');
 */
final class RecurringBuilder
{
    /**
     * @param  array{enabled: bool, tables: array<string, string>, models: array<string, class-string>, auto_record_transactions: bool}  $persistence
     */
    public function __construct(
        private readonly EcommClient $client,
        private readonly Dispatcher $events,
        private readonly MerchantConfig $merchant,
        private readonly array $persistence,
    ) {}

    /**
     * Register a recurring template AND charge the first payment through
     * the standard 3DS card-entry flow (command=z).
     */
    public function registerWithSmsFirstPayment(): RegisterWithSmsFirstPayment
    {
        return new RegisterWithSmsFirstPayment($this->client, $this->events, $this->merchant, $this->persistence);
    }

    /**
     * Register a recurring template AND authorize the first payment (DMS
     * hold, command=d). Capture separately via DmsCompletion.
     */
    public function registerWithDmsFirstPayment(): RegisterWithDmsFirstPayment
    {
        return new RegisterWithDmsFirstPayment($this->client, $this->events, $this->merchant, $this->persistence);
    }

    /**
     * Register a recurring template without charging the first payment
     * (command=p). Client still completes a card-entry flow so the card
     * can be captured for future use.
     */
    public function registerWithoutFirstPayment(): RegisterWithoutFirstPayment
    {
        return new RegisterWithoutFirstPayment($this->client, $this->events, $this->merchant, $this->persistence);
    }

    public function registerWithFirstPayment(): RegisterWithSmsFirstPayment
    {
        return $this->registerWithSmsFirstPayment();
    }

    /**
     * Execute a previously registered recurring payment (command=e).
     * Returns the execute builder so you can set amount/currency fluently.
     */
    public function execute(string $billerClientId): Execute
    {
        return (new Execute($this->client, $this->events, $this->merchant, $this->persistence))
            ->billerClientId($billerClientId);
    }

    /**
     * Delete a recurring template at the bank side (command=x).
     */
    public function delete(string $billerClientId): DeleteRecurring
    {
        return (new DeleteRecurring($this->client, $this->events, $this->merchant, $this->persistence))
            ->billerClientId($billerClientId);
    }
}
