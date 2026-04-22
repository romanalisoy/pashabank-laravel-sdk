<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Romanalisoy\PashaBank\Events\PaymentCompleted;
use Romanalisoy\PashaBank\Events\PaymentFailed;
use Romanalisoy\PashaBank\Facades\PashaBank;
use Romanalisoy\PashaBank\Models\PashaTransaction;

beforeEach(function (): void {
    PashaTransaction::create([
        'merchant_key' => 'main',
        'transaction_id' => 'cb-trans-1',
        'command' => 'v',
        'status' => PashaTransaction::STATUS_PENDING,
        'amount_minor' => 1980,
        'currency' => '944',
    ]);
});

it('redirects to success URL on approved transaction', function (): void {
    config()->set('pashabank.callback.response', 'redirect');
    config()->set('pashabank.callback.success_url', '/paid');

    Event::fake();

    PashaBank::fake()->fakeCallback('cb-trans-1', [
        'RESULT' => 'OK',
        'RESULT_CODE' => '000',
        'RRN' => '123456789012',
        'APPROVAL_CODE' => '654321',
    ]);

    $this->post('/pashabank/callback', ['trans_id' => 'cb-trans-1'])
        ->assertRedirect('/paid?trans_id=cb-trans-1');

    Event::assertDispatched(PaymentCompleted::class);

    $tx = PashaTransaction::query()->firstWhere('transaction_id', 'cb-trans-1');
    expect($tx->status)->toBe(PashaTransaction::STATUS_OK)
        ->and($tx->rrn)->toBe('123456789012')
        ->and($tx->approval_code)->toBe('654321');
});

it('redirects to failure URL on declined transaction', function (): void {
    config()->set('pashabank.callback.response', 'redirect');
    config()->set('pashabank.callback.failure_url', '/failed');

    Event::fake();

    PashaBank::fake()->fakeCallback('cb-trans-1', [
        'RESULT' => 'FAILED',
        'RESULT_CODE' => '116',
    ]);

    $this->post('/pashabank/callback', ['trans_id' => 'cb-trans-1'])
        ->assertRedirect('/failed?trans_id=cb-trans-1');

    Event::assertDispatched(PaymentFailed::class);

    $tx = PashaTransaction::query()->firstWhere('transaction_id', 'cb-trans-1');
    expect($tx->status)->toBe(PashaTransaction::STATUS_FAILED)
        ->and($tx->result_code)->toBe('116');
});

it('returns JSON when configured', function (): void {
    config()->set('pashabank.callback.response', 'json');

    PashaBank::fake()->fakeCallback('cb-trans-1', [
        'RESULT' => 'OK',
        'RESULT_CODE' => '000',
    ]);

    $this->post('/pashabank/callback', ['trans_id' => 'cb-trans-1'])
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'transaction_id' => 'cb-trans-1',
        ]);
});

it('fails gracefully when trans_id is missing', function (): void {
    config()->set('pashabank.callback.response', 'redirect');

    $this->post('/pashabank/callback', [])
        ->assertRedirect();
});
