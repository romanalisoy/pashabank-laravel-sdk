<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Client;

use Romanalisoy\PashaBank\Exceptions\MerchantException;

/**
 * The ECOMM module speaks a plain "KEY: VALUE\n" payload (not JSON). Every
 * value is a string; callers cast as needed. This class just shields the
 * rest of the SDK from that quirky format and surfaces the standard "error"
 * field as a MerchantException.
 */
final class Response
{
    /**
     * @param  array<string, string>  $fields
     */
    private function __construct(
        private readonly array $fields,
        private readonly string $raw,
    ) {}

    public static function parse(string $raw): self
    {
        $fields = [];

        foreach (preg_split('/\r?\n/', trim($raw)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            // The separator between key and value is the first ": " — values
            // may legitimately contain colons (URLs, timestamps).
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = ltrim(substr($line, $pos + 1));
            $fields[$key] = $value;
        }

        return new self($fields, $raw);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->fields);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->fields[$key] ?? $default;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->fields;
    }

    public function raw(): string
    {
        return $this->raw;
    }

    /**
     * Throw a MerchantException when the response carries an "error" field.
     * Return $this unchanged otherwise, so this can be chained fluently:
     *
     *     $response->parse($body)->throwIfErrored()->get('TRANSACTION_ID');
     */
    public function throwIfErrored(): self
    {
        if ($this->has('error')) {
            throw MerchantException::fromErrorField($this->get('error') ?? '', $this->fields);
        }

        return $this;
    }
}
