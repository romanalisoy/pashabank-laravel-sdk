<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Testing;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;
use Romanalisoy\PashaBank\PashaBankManager;

/**
 * Drop-in replacement for the real manager in tests. Swaps the HTTP
 * transport for Http::fake() — this leaves every operation fully
 * functional (validation, event dispatch, persistence) while returning a
 * canned bank response you control.
 *
 * Typical usage:
 *
 *   PashaBank::fake()->willReturn([
 *       'TRANSACTION_ID' => 'trans-1',
 *   ])->forCommand('v');
 *
 *   $this->post('/checkout', [...]);
 *
 *   PashaBank::assertCommandSent('v');
 *
 * Swapping behaviour per command lets assertions stay close to the code
 * they describe — no need to build response bodies by hand.
 */
class PashaBankFake extends PashaBankManager
{
    /** @var array<int, array{command: string|null, parameters: array<string, mixed>}> */
    protected array $sentRequests = [];

    /** @var array<string, string> Command letter => canned response body. */
    protected array $responses = [];

    protected string $defaultResponse = "RESULT: OK\nRESULT_CODE: 000\nTRANSACTION_ID: test-trans-id\n";

    public function bind(): self
    {
        Http::fake(fn ($request) => Http::response($this->respondTo($request), 200));

        $this->sentRequests = [];

        return $this;
    }

    /**
     * Register a canned response for a specific command letter. Pass an
     * array of KEY => VALUE entries; the fake converts them into the
     * bank's KEY: VALUE\n format.
     *
     * @param  array<string, string>  $fields
     */
    public function willReturnForCommand(string $command, array $fields): self
    {
        $this->responses[$command] = $this->encode($fields);

        return $this;
    }

    /**
     * Register a canned response used when no command-specific one
     * matches.
     *
     * @param  array<string, string>  $fields
     */
    public function willReturn(array $fields): self
    {
        $this->defaultResponse = $this->encode($fields);

        return $this;
    }

    /**
     * Simulate an erroring response for the next matching command.
     */
    public function willError(string $command, string $message): self
    {
        $this->responses[$command] = $this->encode(['error' => $message]);

        return $this;
    }

    /**
     * Assert a request was sent to MerchantHandler for the given command
     * letter. Pass a closure to inspect the raw parameter array.
     *
     * @param  (callable(array<string, mixed>): bool)|null  $predicate
     */
    public function assertCommandSent(string $command, ?callable $predicate = null): self
    {
        foreach ($this->sentRequests as $record) {
            if ($record['command'] !== $command) {
                continue;
            }

            if ($predicate === null || $predicate($record['parameters']) === true) {
                Assert::assertNotEmpty($record, 'A matching PASHA Bank request was captured.');

                return $this;
            }
        }

        Assert::fail(sprintf('No PASHA Bank request was sent for command "%s".', $command));
    }

    public function assertNothingSent(): self
    {
        Assert::assertSame([], $this->sentRequests, 'Expected no PASHA Bank requests, but some were sent.');

        return $this;
    }

    /** @return array<int, array{command: string|null, parameters: array<string, mixed>}> */
    public function sentRequests(): array
    {
        return $this->sentRequests;
    }

    /**
     * Pretend the bank just POSTed the callback for a transaction,
     * returning the given status fields on the subsequent command=c
     * call. Useful for higher-level Feature tests that drive the
     * controller.
     *
     * @param  array<string, string>  $status
     */
    public function fakeCallback(string $transactionId, array $status): self
    {
        $this->responses['c'] = $this->encode(array_merge([
            'RESULT' => 'OK',
            'RESULT_CODE' => '000',
        ], $status));

        return $this;
    }

    protected function respondTo(mixed $request): string
    {
        $body = method_exists($request, 'body') ? (string) $request->body() : '';
        parse_str($body, $parameters);

        $command = isset($parameters['command']) ? (string) $parameters['command'] : null;

        $this->sentRequests[] = [
            'command' => $command,
            'parameters' => $parameters,
        ];

        if ($command !== null && isset($this->responses[$command])) {
            return $this->responses[$command];
        }

        return $this->defaultResponse;
    }

    /**
     * @param  array<string, string>  $fields
     */
    protected function encode(array $fields): string
    {
        $lines = [];
        foreach ($fields as $key => $value) {
            $lines[] = sprintf('%s: %s', $key, $value);
        }

        return implode("\n", $lines);
    }
}
