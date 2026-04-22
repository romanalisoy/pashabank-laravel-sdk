<?php

declare(strict_types=1);

use Romanalisoy\PashaBank\Exceptions\ValidationException;
use Romanalisoy\PashaBank\Support\Amount;

it('converts decimals to minor units', function (int|float|string $input, int $expected): void {
    expect(Amount::toMinor($input))->toBe($expected);
})->with([
    [19.80, 1980],
    ['19.80', 1980],
    [1.99, 199],
    ['1.99', 199],
    [0, 0],
    [1980, 1980],
    [100.00, 10000],
]);

it('formats minor units back to the bank decimal string', function (): void {
    expect(Amount::toBankString(1980))->toBe('19.80');
    expect(Amount::toBankString(199))->toBe('1.99');
    expect(Amount::toBankString(0))->toBe('0.00');
    expect(Amount::toBankString(100000))->toBe('1000.00');
});

it('rejects negative amounts', function (): void {
    Amount::toMinor(-1);
})->throws(ValidationException::class);

it('rejects non-numeric strings', function (): void {
    Amount::toMinor('abc');
})->throws(ValidationException::class);

it('rejects strings with three decimal places', function (): void {
    Amount::toMinor('1.999');
})->throws(ValidationException::class);
