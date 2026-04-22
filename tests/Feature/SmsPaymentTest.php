<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Romanalisoy\PashaBank\Events\PaymentRegistered;
use Romanalisoy\PashaBank\Facades\PashaBank;
use Romanalisoy\PashaBank\Models\PashaTransaction;

it('registers an SMS payment and returns a redirect URL', function (): void {
    Event::fake();

    PashaBank::fake()->willReturnForCommand('v', [
        'TRANSACTION_ID' => 'test-trans-1',
    ]);

    $registration = PashaBank::sms()
        ->amount(19.80)
        ->currency('AZN')
        ->description('Order #1')
        ->clientIp('127.0.0.1')
        ->register();

    expect($registration->transactionId)->toBe('test-trans-1')
        ->and($registration->redirectUrl)->toContain('trans_id=test-trans-1');

    Event::assertDispatched(PaymentRegistered::class, function (PaymentRegistered $event): bool {
        return $event->transactionId === 'test-trans-1'
            && $event->command === 'v'
            && $event->amountMinor === 1980
            && $event->currency === '944';
    });
});

it('persists the transaction in minor units', function (): void {
    PashaBank::fake()->willReturnForCommand('v', [
        'TRANSACTION_ID' => 'test-trans-2',
    ]);

    PashaBank::sms()
        ->amount(1.99)
        ->currency('AZN')
        ->description('Order #2')
        ->clientIp('10.0.0.5')
        ->register();

    $tx = PashaTransaction::query()->firstWhere('transaction_id', 'test-trans-2');
    expect($tx)->not->toBeNull()
        ->and($tx->amount_minor)->toBe(199)
        ->and($tx->currency)->toBe('944')
        ->and($tx->status)->toBe(PashaTransaction::STATUS_PENDING)
        ->and($tx->command)->toBe('v');
});

it('sends the correct command and amount string to the bank', function (): void {
    $fake = PashaBank::fake()->willReturnForCommand('v', ['TRANSACTION_ID' => 'x']);

    PashaBank::sms()->amount(19.80)->currency('AZN')->register();

    $fake->assertCommandSent('v', function (array $parameters): bool {
        return $parameters['amount'] === '19.80'
            && $parameters['currency'] === '944'
            && $parameters['msg_type'] === 'SMS';
    });
});
