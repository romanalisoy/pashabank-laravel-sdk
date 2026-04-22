<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations;

use Romanalisoy\PashaBank\Data\PaymentRegistration;
use Romanalisoy\PashaBank\Events\PaymentRegistered;
use Romanalisoy\PashaBank\Exceptions\MerchantException;
use Romanalisoy\PashaBank\Models\PashaTransaction;
use Romanalisoy\PashaBank\Support\Amount;

/**
 * Single Message (SMS) Transaction without given card data — command=v.
 *
 * PDF §4.1. The call returns TRANSACTION_ID; the client's browser is
 * then redirected to ClientHandler?trans_id=... to enter card details.
 * After 3DS and authorization the bank POSTs back to the merchant's
 * RETURN_OK_URL, at which point the merchant must call command=c (see
 * TransactionResult) to finalize the payment.
 */
class SmsPayment extends Operation
{
    public function register(): PaymentRegistration
    {
        $amountMinor = $this->requireAmount();
        $currency = $this->requireCurrency();

        $response = $this->send(array_merge($this->parameters(), [
            'command' => 'v',
            'amount' => Amount::toBankString($amountMinor),
            'currency' => $currency,
            'msg_type' => 'SMS',
        ]));

        $transactionId = $response->get('TRANSACTION_ID', '');
        if ($transactionId === '' || $transactionId === null) {
            // Defensive: the bank returns TRANSACTION_ID on success; if the
            // line came back empty without an "error" field, surface the
            // raw body so operators can inspect it.
            throw new MerchantException(
                'PASHA Bank returned an empty TRANSACTION_ID.',
                $response->all()
            );
        }

        $model = $this->recordTransaction($transactionId, $amountMinor, $currency);

        $this->events->dispatch(new PaymentRegistered(
            merchant: $this->merchant,
            transactionId: $transactionId,
            command: 'v',
            amountMinor: $amountMinor,
            currency: $currency,
            transaction: $model,
        ));

        return new PaymentRegistration(
            transactionId: $transactionId,
            redirectUrl: $this->merchant->clientHandlerUrl.'?trans_id='.urlencode($transactionId),
            transaction: $model,
        );
    }

    protected function recordTransaction(string $transactionId, int $amountMinor, string $currency): ?PashaTransaction
    {
        if (! $this->persistenceEnabled()) {
            return null;
        }

        /** @var class-string<PashaTransaction> $class */
        $class = $this->transactionModelClass();

        /** @var PashaTransaction $model */
        $model = new $class;
        $model->fill([
            'merchant_key' => $this->merchant->key,
            'transaction_id' => $transactionId,
            'command' => 'v',
            'status' => PashaTransaction::STATUS_PENDING,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'client_ip' => $this->clientIp ?? $this->fallbackIp(),
            'description' => $this->description,
            'payable_type' => $this->payable?->getMorphClass(),
            'payable_id' => $this->payable?->getKey(),
        ]);
        $model->save();

        return $model;
    }
}
