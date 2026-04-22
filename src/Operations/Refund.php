<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations;

use Romanalisoy\PashaBank\Data\OperationResult;
use Romanalisoy\PashaBank\Events\PaymentRefunded;
use Romanalisoy\PashaBank\Models\PashaTransaction;
use Romanalisoy\PashaBank\Support\Amount;

/**
 * Refund — command=k. Full refund when no amount is given; partial refund
 * when amount < original. The bank returns a REFUND_TRANS_ID which the
 * result DTO carries for later reversal, should that be needed.
 *
 * PDF §4.8.
 */
class Refund extends Operation
{
    public function execute(): OperationResult
    {
        $transactionId = $this->requireTransactionId();

        $params = array_merge($this->parameters(), [
            'command' => 'k',
            'trans_id' => $transactionId,
        ]);

        if ($this->amountMinor !== null) {
            $params['amount'] = Amount::toBankString($this->amountMinor);
        }

        $response = $this->send($params);
        $result = OperationResult::fromResponse($response);

        $model = $this->updatePersisted($transactionId, $result);

        $this->events->dispatch(new PaymentRefunded(
            transactionId: $transactionId,
            amountMinor: $this->amountMinor ?? ($model instanceof PashaTransaction ? $model->amount_minor : 0),
            result: $result,
            transaction: $model,
        ));

        return $result;
    }

    protected function updatePersisted(string $transactionId, OperationResult $result): ?PashaTransaction
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

        if ($result->isSuccessful()) {
            $model->forceFill([
                'status' => PashaTransaction::STATUS_REFUNDED,
                'result_code' => $result->resultCode,
            ])->save();
        }

        return $model;
    }
}
