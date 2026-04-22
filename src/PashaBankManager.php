<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Romanalisoy\PashaBank\Client\EcommClient;
use Romanalisoy\PashaBank\Client\MerchantConfig;
use Romanalisoy\PashaBank\Exceptions\ConfigurationException;
use Romanalisoy\PashaBank\Operations\CloseDay;
use Romanalisoy\PashaBank\Operations\DmsAuthorization;
use Romanalisoy\PashaBank\Operations\DmsCompletion;
use Romanalisoy\PashaBank\Operations\Recurring\RecurringBuilder;
use Romanalisoy\PashaBank\Operations\Refund;
use Romanalisoy\PashaBank\Operations\Reversal;
use Romanalisoy\PashaBank\Operations\SmsPayment;
use Romanalisoy\PashaBank\Operations\TransactionResult;
use Romanalisoy\PashaBank\Testing\PashaBankFake;

/**
 * Entry point behind the PashaBank facade. Hands out fluent operation
 * builders pre-wired to a specific merchant, and holds the tiny amount of
 * state needed for multi-merchant flows (active merchant key).
 *
 * The manager itself never talks to the bank — that happens on the
 * operation when ->register()/->execute()/->charge() is called. Keeping
 * the split narrow makes the API discoverable from IDE autocomplete.
 */
class PashaBankManager
{
    private ?string $activeMerchant = null;

    public function __construct(
        protected readonly ConfigRepository $config,
        protected readonly EcommClient $client,
        protected readonly Dispatcher $events,
    ) {}

    /**
     * Swap the merchant for the NEXT call only. Chained syntax:
     *
     *     PashaBank::merchant('second')->sms()->amount(10)->register();
     *
     * After the operation is built the selection resets, so subsequent
     * calls fall back to the default merchant.
     */
    public function merchant(string $key): self
    {
        $this->activeMerchant = $key;

        return $this;
    }

    public function sms(): SmsPayment
    {
        return new SmsPayment($this->client, $this->events, $this->resolveMerchant(), $this->persistenceConfig());
    }

    public function dms(): DmsAuthorization
    {
        return new DmsAuthorization($this->client, $this->events, $this->resolveMerchant(), $this->persistenceConfig());
    }

    public function dmsComplete(string $transactionId): DmsCompletion
    {
        return (new DmsCompletion($this->client, $this->events, $this->resolveMerchant(), $this->persistenceConfig()))
            ->transactionId($transactionId);
    }

    public function reversal(string $transactionId): Reversal
    {
        return (new Reversal($this->client, $this->events, $this->resolveMerchant(), $this->persistenceConfig()))
            ->transactionId($transactionId);
    }

    public function refund(string $transactionId): Refund
    {
        return (new Refund($this->client, $this->events, $this->resolveMerchant(), $this->persistenceConfig()))
            ->transactionId($transactionId);
    }

    public function completion(string $transactionId): TransactionResult
    {
        return (new TransactionResult($this->client, $this->events, $this->resolveMerchant(), $this->persistenceConfig()))
            ->transactionId($transactionId);
    }

    public function recurring(): RecurringBuilder
    {
        return new RecurringBuilder($this->client, $this->events, $this->resolveMerchant(), $this->persistenceConfig());
    }

    public function closeDay(): CloseDay
    {
        return new CloseDay($this->client, $this->events, $this->resolveMerchant(), $this->persistenceConfig());
    }

    /**
     * Build the redirect URL the client's browser must visit after a
     * successful payment registration.
     */
    public function clientHandlerUrl(string $transactionId, ?string $merchant = null): string
    {
        $merchant = $this->resolveMerchant($merchant);

        return $merchant->clientHandlerUrl.'?trans_id='.urlencode($transactionId);
    }

    /**
     * Replace the real implementation with an in-memory fake. Tests call
     * this once at setUp(); production code never reaches here.
     */
    public function fake(): PashaBankFake
    {
        $fake = new PashaBankFake($this->config, $this->client, $this->events);
        $fake->bind();
        app()->instance(self::class, $fake);

        return $fake;
    }

    /**
     * Resolve the merchant configuration for the current (or explicitly
     * requested) merchant. Resets the active merchant after each call so
     * ->merchant('x') only affects the next chain.
     */
    public function resolveMerchant(?string $key = null): MerchantConfig
    {
        $key ??= $this->activeMerchant ?? (string) $this->config->get('pashabank.default', 'main');
        $this->activeMerchant = null;

        /** @var array<string, array<string, mixed>> $merchants */
        $merchants = (array) $this->config->get('pashabank.merchants', []);

        if (! isset($merchants[$key])) {
            throw ConfigurationException::missingMerchant($key);
        }

        return MerchantConfig::fromArray($key, $merchants[$key], $this->currentEndpoints());
    }

    /**
     * @return array{merchant_handler: string|null, client_handler: string|null}
     */
    private function currentEndpoints(): array
    {
        $env = (string) $this->config->get('pashabank.environment', 'production');

        /** @var array{merchant_handler?: string|null, client_handler?: string|null} $endpoints */
        $endpoints = (array) $this->config->get("pashabank.endpoints.$env", []);

        return [
            'merchant_handler' => $endpoints['merchant_handler'] ?? null,
            'client_handler' => $endpoints['client_handler'] ?? null,
        ];
    }

    /**
     * @return array{enabled: bool, tables: array<string, string>, models: array<string, class-string>, auto_record_transactions: bool}
     */
    private function persistenceConfig(): array
    {
        /** @var array{enabled?: bool, tables?: array<string, string>, models?: array<string, class-string>, auto_record_transactions?: bool} $persistence */
        $persistence = (array) $this->config->get('pashabank.persistence', []);

        return [
            'enabled' => (bool) ($persistence['enabled'] ?? true),
            'tables' => $persistence['tables'] ?? [],
            'models' => $persistence['models'] ?? [],
            'auto_record_transactions' => (bool) ($persistence['auto_record_transactions'] ?? true),
        ];
    }
}
