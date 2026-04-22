<?php

declare(strict_types=1);

use Romanalisoy\PashaBank\Client\Response;
use Romanalisoy\PashaBank\Exceptions\MerchantException;

it('parses standard key-value lines', function (): void {
    $raw = "TRANSACTION_ID: abc123\nRESULT: OK\nRESULT_CODE: 000\n";
    $response = Response::parse($raw);

    expect($response->get('TRANSACTION_ID'))->toBe('abc123')
        ->and($response->get('RESULT'))->toBe('OK')
        ->and($response->get('RESULT_CODE'))->toBe('000');
});

it('handles values that contain colons', function (): void {
    $raw = "url: https://example.test/cb?trans_id=xx\n";
    $response = Response::parse($raw);

    expect($response->get('url'))->toBe('https://example.test/cb?trans_id=xx');
});

it('ignores blank lines and trims whitespace', function (): void {
    $raw = "\n  RESULT :    OK   \n\n";
    $response = Response::parse($raw);

    expect($response->get('RESULT'))->toBe('OK   ');
});

it('throws MerchantException when the error field is present', function (): void {
    $response = Response::parse("error: no transaction id\n");

    $response->throwIfErrored();
})->throws(MerchantException::class, 'no transaction id');

it('does not throw on successful responses', function (): void {
    $response = Response::parse("RESULT: OK\n");

    expect($response->throwIfErrored()->get('RESULT'))->toBe('OK');
});
