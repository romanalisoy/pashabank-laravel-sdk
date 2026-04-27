<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations\Recurring;

use Romanalisoy\PashaBank\Data\PaymentRegistration;
use Romanalisoy\PashaBank\Events\PaymentRegistered;
use Romanalisoy\PashaBank\Exceptions\MerchantException;
use Romanalisoy\PashaBank\Models\PashaRecurring;
use Romanalisoy\PashaBank\Models\PashaTransaction;
use Romanalisoy\PashaBank\Operations\Operation;
use Romanalisoy\PashaBank\Support\Amount;

/**
 * Recurring registration with first DMS payment — command=d. Authorizes
 * (holds) the first payment instead of fully charging it. Capture later
 * with DmsCompletion. PDF §4.14. Domestic cards only.
 */
class RegisterWithDmsFirstPayment extends Operation
{
    use RecurringAware;

    public function register(): PaymentRegistration
    {
        $amountMinor = $this->requireAmount();
        $currency = $this->requireCurrency();
        $billerClientId = $this->requireBillerClientId();
        $expiry = $this->requireExpiry();

        $recurring = $this->persistRecurring($billerClientId, $expiry);

        $response = $this->send(array_merge($this->parameters(), [
            'command' => 'd',
            'amount' => Amount::toBankString($amountMinor),
            'currency' => $currency,
            'msg_type' => 'DMS',
            'biller_client_id' => $billerClientId,
            'perspayee_expiry' => $expiry,
            'perspayee_gen' => '1',
            'perspayee_overwrite' => $this->overwriteTemplate ? '1' : '0',
        ]));

        $transactionId = $response->get('TRANSACTION_ID', '');
        if ($transactionId === '' || $transactionId === null) {
            throw new MerchantException('PASHA Bank returned an empty TRANSACTION_ID.', $response->all());
        }

        $transaction = $this->recordTransaction($transactionId, $amountMinor, $currency, $recurring?->getKey());

        $this->events->dispatch(new PaymentRegistered(
            merchant: $this->merchant,
            transactionId: $transactionId,
            command: 'd',
            amountMinor: $amountMinor,
            currency: $currency,
            transaction: $transaction,
        ));

        return new PaymentRegistration(
            transactionId: $transactionId,
            redirectUrl: $this->merchant->clientHandlerUrl.'?trans_id='.urlencode($transactionId),
            transaction: $transaction,
        );
    }

    protected function persistRecurring(string $billerClientId, string $expiry): ?PashaRecurring
    {
        if (! $this->persistence['enabled']) {
            return null;
        }

        /** @var class-string<PashaRecurring> $class */
        $class = $this->recurringModelClass();

        /** @var PashaRecurring|null $existing */
        $existing = $class::query()->where('biller_client_id', $billerClientId)->first();
        if ($existing instanceof PashaRecurring) {
            $existing->forceFill([
                'expiry' => $expiry,
                'status' => PashaRecurring::STATUS_PENDING,
                'merchant_key' => $this->merchant->key,
                'owner_type' => $this->payable?->getMorphClass(),
                'owner_id' => $this->payable?->getKey(),
            ])->save();

            return $existing;
        }

        /** @var PashaRecurring $model */
        $model = new $class;
        $model->fill([
            'merchant_key' => $this->merchant->key,
            'biller_client_id' => $billerClientId,
            'expiry' => $expiry,
            'status' => PashaRecurring::STATUS_PENDING,
            'owner_type' => $this->payable?->getMorphClass(),
            'owner_id' => $this->payable?->getKey(),
        ]);
        $model->save();

        return $model;
    }

    protected function recordTransaction(string $transactionId, int $amountMinor, string $currency, int|string|null $recurringId): ?PashaTransaction
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
            'command' => 'd',
            'status' => PashaTransaction::STATUS_PENDING,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'client_ip' => $this->clientIp ?? $this->fallbackIp(),
            'description' => $this->description,
            'payable_type' => $this->payable?->getMorphClass(),
            'payable_id' => $this->payable?->getKey(),
            'recurring_id' => $recurringId,
            'meta' => $this->buildMetaForPersistence(),
        ]);
        $model->save();

        return $model;
    }
}
