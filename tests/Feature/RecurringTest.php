<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Romanalisoy\PashaBank\Events\RecurringExecuted;
use Romanalisoy\PashaBank\Events\RecurringRegistered;
use Romanalisoy\PashaBank\Facades\PashaBank;
use Romanalisoy\PashaBank\Models\PashaRecurring;
use Romanalisoy\PashaBank\Models\PashaTransaction;

it('registers a recurring template with first SMS payment', function (): void {
    Event::fake();

    PashaBank::fake()->willReturnForCommand('z', [
        'TRANSACTION_ID' => 'rec-trans-1',
    ]);

    $registration = PashaBank::recurring()
        ->registerWithSmsFirstPayment()
        ->amount(9.99)
        ->currency('AZN')
        ->billerClientId('sub-42')
        ->expiry('1231')
        ->description('Monthly subscription')
        ->clientIp('127.0.0.1')
        ->register();

    expect($registration->transactionId)->toBe('rec-trans-1');

    $recurring = PashaRecurring::query()->firstWhere('biller_client_id', 'sub-42');
    expect($recurring)->not->toBeNull()
        ->and($recurring->expiry)->toBe('1231')
        ->and($recurring->status)->toBe(PashaRecurring::STATUS_PENDING);

    $transaction = PashaTransaction::query()->firstWhere('transaction_id', 'rec-trans-1');
    expect($transaction->command)->toBe('z')
        ->and($transaction->amount_minor)->toBe(999)
        ->and($transaction->recurring_id)->toBe($recurring->id);
});

it('registers without first payment and marks active on success', function (): void {
    Event::fake();

    PashaBank::fake()->willReturnForCommand('p', [
        'RESULT' => 'OK',
        'RESULT_CODE' => '000',
    ]);

    $result = PashaBank::recurring()
        ->registerWithoutFirstPayment()
        ->currency('AZN')
        ->billerClientId('sub-50')
        ->expiry('1231')
        ->clientIp('127.0.0.1')
        ->register();

    expect($result->isSuccessful())->toBeTrue();

    $recurring = PashaRecurring::query()->firstWhere('biller_client_id', 'sub-50');
    expect($recurring->status)->toBe(PashaRecurring::STATUS_ACTIVE);

    Event::assertDispatched(RecurringRegistered::class);
});

it('executes a recurring charge and dispatches event', function (): void {
    PashaRecurring::create([
        'merchant_key' => 'main',
        'biller_client_id' => 'sub-77',
        'expiry' => '1231',
        'status' => PashaRecurring::STATUS_ACTIVE,
    ]);

    Event::fake();

    PashaBank::fake()->willReturnForCommand('e', [
        'RESULT' => 'OK',
        'RESULT_CODE' => '000',
        'TRANSACTION_ID' => 'rec-charge-1',
        'RRN' => '111222333444',
        'APPROVAL_CODE' => '555666',
    ]);

    $result = PashaBank::recurring()
        ->execute('sub-77')
        ->amount(9.99)
        ->currency('AZN')
        ->clientIp('127.0.0.1')
        ->charge();

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->rrn)->toBe('111222333444');

    Event::assertDispatched(RecurringExecuted::class);

    $tx = PashaTransaction::query()->firstWhere('transaction_id', 'rec-charge-1');
    expect($tx->status)->toBe(PashaTransaction::STATUS_OK)
        ->and($tx->amount_minor)->toBe(999);
});

it('deletes a recurring template and marks it deleted', function (): void {
    PashaRecurring::create([
        'merchant_key' => 'main',
        'biller_client_id' => 'sub-dead',
        'expiry' => '1231',
        'status' => PashaRecurring::STATUS_ACTIVE,
    ]);

    PashaBank::fake()->willReturnForCommand('x', [
        'RESULT' => 'OK',
        'RESULT_CODE' => '000',
    ]);

    $result = PashaBank::recurring()->delete('sub-dead')->execute();

    expect($result->isSuccessful())->toBeTrue();

    $rec = PashaRecurring::query()->firstWhere('biller_client_id', 'sub-dead');
    expect($rec->status)->toBe(PashaRecurring::STATUS_DELETED);
});
