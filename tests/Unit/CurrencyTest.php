<?php

declare(strict_types=1);

use Romanalisoy\PashaBank\Exceptions\ValidationException;
use Romanalisoy\PashaBank\Support\Currency;

it('resolves alpha codes to numeric', function (): void {
    expect(Currency::toNumeric('AZN'))->toBe('944')
        ->and(Currency::toNumeric('USD'))->toBe('840')
        ->and(Currency::toNumeric('EUR'))->toBe('978')
        ->and(Currency::toNumeric('aZn'))->toBe('944');
});

it('accepts numeric codes when they are known', function (): void {
    expect(Currency::toNumeric('944'))->toBe('944');
    expect(Currency::toNumeric(944))->toBe('944');
});

it('rejects unknown currencies', function (): void {
    Currency::toNumeric('XYZ');
})->throws(ValidationException::class);

it('rejects unknown numeric codes', function (): void {
    Currency::toNumeric('999');
})->throws(ValidationException::class);

it('converts numeric back to alpha', function (): void {
    expect(Currency::toAlpha('944'))->toBe('AZN');
});
