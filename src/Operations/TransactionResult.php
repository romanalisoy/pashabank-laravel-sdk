<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations;

use Romanalisoy\PashaBank\Data\TransactionStatus;
use Romanalisoy\PashaBank\Events\PaymentCompleted;
use Romanalisoy\PashaBank\Events\PaymentFailed;
use Romanalisoy\PashaBank\Models\PashaTransaction;

/**
 * Transaction Result / completion — command=c. This is the call that
 * finalises a payment on the bank side; without it the bank reverses the
 * hold after ~3 minutes. The SDK's default CallbackController issues this
 * call automatically.
 *
 * PDF §4.19.
 */
class TransactionResult extends Operation
{
    public function get(): TransactionStatus
    {
        $transactionId = $this->requireTransactionId();

        $response = $this->send(array_merge($this->parameters(), [
            'command' => 'c',
            'trans_id' => $transactionId,
        ]));

        $status = TransactionStatus::fromResponse($response);

        $model = $this->updatePersisted($transactionId, $status);

        if ($status->isSuccessful()) {
            $this->events->dispatch(new PaymentCompleted($transactionId, $status, $model));
        } elseif ($status->isFailed() || $status->isReversed()) {
            $this->events->dispatch(new PaymentFailed($transactionId, $status, $model));
        }

        return $status;
    }

    protected function updatePersisted(string $transactionId, TransactionStatus $status): ?PashaTransaction
    {
        if (! $this->persistenceEnabled()) {
            return null;
        }

        /** @var class-string<PashaTransaction> $class */
        $class = $this->transactionModelClass();

        $model = $class::query()->where('transaction_id', $transactionId)->first();
        if (! $model instanceof PashaTransaction) {
            return null;
        }

        if ($status->isSuccessful()) {
            $model->markOk(
                approvalCode: $status->approvalCode,
                rrn: $status->rrn,
                cardMask: $status->cardMask,
                resultCode: $status->resultCode,
                threeDs: $status->threeDSecure,
            );
        } elseif ($status->isFailed() || $status->isReversed()) {
            $model->markFailed($status->resultCode);
        }

        return $model;
    }
}
