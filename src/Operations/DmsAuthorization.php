<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations;

use Romanalisoy\PashaBank\Data\PaymentRegistration;
use Romanalisoy\PashaBank\Events\PaymentRegistered;
use Romanalisoy\PashaBank\Exceptions\MerchantException;
use Romanalisoy\PashaBank\Models\PashaTransaction;
use Romanalisoy\PashaBank\Support\Amount;

/**
 * Dual Message (DMS) Authorisation — command=a. Holds funds on the card
 * without capturing them. Use DmsCompletion to settle later; otherwise
 * the hold expires.
 *
 * PDF §4.4.
 */
class DmsAuthorization extends Operation
{
    public function authorize(): PaymentRegistration
    {
        $amountMinor = $this->requireAmount();
        $currency = $this->requireCurrency();

        $response = $this->send(array_merge($this->parameters(), [
            'command' => 'a',
            'amount' => Amount::toBankString($amountMinor),
            'currency' => $currency,
            'msg_type' => 'DMS',
        ]));

        $transactionId = $response->get('TRANSACTION_ID', '');
        if ($transactionId === '' || $transactionId === null) {
            throw new MerchantException('PASHA Bank returned an empty TRANSACTION_ID.', $response->all());
        }

        $model = $this->recordTransaction($transactionId, $amountMinor, $currency);

        $this->events->dispatch(new PaymentRegistered(
            merchant: $this->merchant,
            transactionId: $transactionId,
            command: 'a',
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
            'command' => 'a',
            'status' => PashaTransaction::STATUS_PENDING,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'client_ip' => $this->clientIp ?? $this->fallbackIp(),
            'description' => $this->description,
            'payable_type' => $this->payable?->getMorphClass(),
            'payable_id' => $this->payable?->getKey(),
            'meta' => $this->buildMetaForPersistence(),
        ]);
        $model->save();

        return $model;
    }
}
