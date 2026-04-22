<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Romanalisoy\PashaBank\Client\EcommClient;
use Romanalisoy\PashaBank\Client\MerchantConfig;
use Romanalisoy\PashaBank\Client\Response;
use Romanalisoy\PashaBank\Exceptions\ValidationException;
use Romanalisoy\PashaBank\Models\PashaRecurring;
use Romanalisoy\PashaBank\Models\PashaTransaction;
use Romanalisoy\PashaBank\Support\Amount;
use Romanalisoy\PashaBank\Support\Currency;

/**
 * Shared surface area for every fluent operation builder. Subclasses
 * describe which fields they collect, then call ->dispatch() to actually
 * reach the bank. Keeping this layer thin keeps each subclass legible —
 * the PDF spec maps almost 1:1 to builder methods.
 *
 * The base class uses protected state so child classes can expose only
 * the fields relevant to their command.
 */
abstract class Operation
{
    protected ?int $amountMinor = null;

    protected ?string $currency = null;

    protected ?string $description = null;

    protected ?string $language = null;

    protected ?string $clientIp = null;

    protected ?string $transactionId = null;

    protected ?Model $payable = null;

    /** @var array<string, scalar> Arbitrary extras the bank allows. */
    protected array $extraParameters = [];

    /**
     * @param  array{enabled: bool, tables: array<string, string>, models: array<string, class-string>, auto_record_transactions: bool}  $persistence
     */
    public function __construct(
        protected readonly EcommClient $client,
        protected readonly Dispatcher $events,
        protected readonly MerchantConfig $merchant,
        protected readonly array $persistence,
    ) {
        $this->currency = $this->merchant->currency;
        $this->language = $this->merchant->language;
    }

    /** Accept decimal (19.80), string ("19.80"), or minor units (1980). */
    public function amount(int|float|string $amount): static
    {
        $this->amountMinor = Amount::toMinor($amount);

        return $this;
    }

    /**
     * Alternative setter when the developer already stores minor units.
     * Kept explicit so reading $op->amountMinor(1980) is unambiguous.
     */
    public function amountMinor(int $minor): static
    {
        $this->amountMinor = Amount::toMinor($minor);

        return $this;
    }

    public function currency(string|int $currency): static
    {
        $this->currency = is_int($currency) ? (string) $currency : strtoupper($currency);

        return $this;
    }

    public function description(string $description): static
    {
        if (strlen($description) > 125) {
            throw ValidationException::descriptionTooLong(strlen($description));
        }

        $this->description = $description;

        return $this;
    }

    public function language(string $language): static
    {
        if (strlen($language) !== 2 || preg_match('/^[a-zA-Z]{2}$/', $language) !== 1) {
            throw ValidationException::invalidLanguage($language);
        }

        $this->language = strtolower($language);

        return $this;
    }

    public function clientIp(string $ip): static
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw ValidationException::invalidIpAddress($ip);
        }

        $this->clientIp = $ip;

        return $this;
    }

    public function transactionId(string $id): static
    {
        $this->transactionId = $id;

        return $this;
    }

    /**
     * Attach an Eloquent model (Order, Invoice, Subscription...) to be
     * persisted polymorphically with the transaction record.
     */
    public function for(Model $model): static
    {
        $this->payable = $model;

        return $this;
    }

    /**
     * Set any extra query parameter the bank supports. Useful for custom
     * merchant attributes, or to override the default msg_type/terminal_id
     * for a single call.
     */
    public function with(string $key, string|int|float|bool $value): static
    {
        $this->extraParameters[$key] = $value;

        return $this;
    }

    /**
     * @param  array<string, scalar>  $extras
     */
    public function withMany(array $extras): static
    {
        $this->extraParameters = array_merge($this->extraParameters, $extras);

        return $this;
    }

    /**
     * Assemble the parameter array to send to /MerchantHandler. Subclasses
     * override this to add command-specific fields. The base fills the
     * fields shared by most commands.
     *
     * @return array<string, scalar|null>
     */
    protected function parameters(): array
    {
        return array_merge([
            'client_ip_addr' => $this->clientIp ?? $this->fallbackIp(),
            'language' => $this->language,
            'description' => $this->description,
            'terminal_id' => $this->merchant->terminalId,
        ], $this->extraParameters);
    }

    protected function fallbackIp(): string
    {
        if (function_exists('request')) {
            $ip = request()->ip();
            if ($ip !== null) {
                return $ip;
            }
        }

        return '127.0.0.1';
    }

    protected function requireAmount(): int
    {
        if ($this->amountMinor === null) {
            throw ValidationException::missingField('amount');
        }

        return $this->amountMinor;
    }

    protected function requireCurrency(): string
    {
        if ($this->currency === null || $this->currency === '') {
            throw ValidationException::missingField('currency');
        }

        return Currency::toNumeric($this->currency);
    }

    protected function requireTransactionId(): string
    {
        if ($this->transactionId === null || $this->transactionId === '') {
            throw ValidationException::missingField('trans_id');
        }

        return $this->transactionId;
    }

    protected function persistenceEnabled(): bool
    {
        return $this->persistence['enabled'] === true
            && $this->persistence['auto_record_transactions'] === true;
    }

    /**
     * @return class-string
     */
    protected function transactionModelClass(): string
    {
        /** @var class-string $class */
        $class = $this->persistence['models']['transaction']
            ?? PashaTransaction::class;

        return $class;
    }

    /**
     * @return class-string
     */
    protected function recurringModelClass(): string
    {
        /** @var class-string $class */
        $class = $this->persistence['models']['recurring']
            ?? PashaRecurring::class;

        return $class;
    }

    /**
     * Send the assembled parameters to the bank and return the parsed
     * response. Errors are raised as exceptions by the client.
     *
     * @param  array<string, scalar|null>  $parameters
     */
    protected function send(array $parameters): Response
    {
        return $this->client->send($this->merchant, $parameters);
    }
}
