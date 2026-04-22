<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations\Recurring;

use Romanalisoy\PashaBank\Data\OperationResult;
use Romanalisoy\PashaBank\Events\RecurringRegistered;
use Romanalisoy\PashaBank\Models\PashaRecurring;
use Romanalisoy\PashaBank\Operations\Operation;

/**
 * Recurring registration without first payment — command=p. The card is
 * captured for future use but no money moves. PDF §4.16. Domestic cards
 * only. Unlike the SMS / DMS flavours, this command returns RESULT +
 * RESULT_CODE immediately (no redirect URL, no 3DS step).
 */
class RegisterWithoutFirstPayment extends Operation
{
    use RecurringAware;

    public function register(): OperationResult
    {
        $currency = $this->requireCurrency();
        $billerClientId = $this->requireBillerClientId();
        $expiry = $this->requireExpiry();

        $recurring = $this->persistRecurring($billerClientId, $expiry);

        $response = $this->send(array_merge($this->parameters(), [
            'command' => 'p',
            'currency' => $currency,
            'msg_type' => 'DMS',
            'biller_client_id' => $billerClientId,
            'perspayee_expiry' => $expiry,
            'perspayee_gen' => '1',
            'perspayee_overwrite' => $this->overwriteTemplate ? '1' : '0',
        ]));

        $result = OperationResult::fromResponse($response);

        if ($result->isSuccessful() && $recurring instanceof PashaRecurring) {
            $recurring->markActive();
        }

        $this->events->dispatch(new RecurringRegistered(
            billerClientId: $billerClientId,
            expiry: $expiry,
            recurring: $recurring,
        ));

        return $result;
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
}
