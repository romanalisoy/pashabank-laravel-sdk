<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations;

use Romanalisoy\PashaBank\Data\OperationResult;
use Romanalisoy\PashaBank\Events\PaymentReversed;
use Romanalisoy\PashaBank\Models\PashaTransaction;
use Romanalisoy\PashaBank\Support\Amount;

/**
 * Reversal — command=r. Full reversal when no amount is given; partial
 * reversal when a smaller amount is provided. suspected_fraud=yes forces
 * a full reversal with fraud flag (partial not allowed in that case).
 *
 * PDF §4.7.
 */
class Reversal extends Operation
{
    protected bool $suspectedFraud = false;

    public function suspectedFraud(bool $flag = true): static
    {
        $this->suspectedFraud = $flag;

        return $this;
    }

    public function execute(): OperationResult
    {
        $transactionId = $this->requireTransactionId();

        $params = array_merge($this->parameters(), [
            'command' => 'r',
            'trans_id' => $transactionId,
        ]);

        if ($this->amountMinor !== null) {
            $params['amount'] = Amount::toBankString($this->amountMinor);
        }

        if ($this->suspectedFraud) {
            $params['suspected_fraud'] = 'yes';
        }

        $response = $this->send($params);
        $result = OperationResult::fromResponse($response);

        $model = $this->updatePersisted($transactionId, $result);

        $this->events->dispatch(new PaymentReversed(
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
                'status' => PashaTransaction::STATUS_REVERSED,
                'result_code' => $result->resultCode,
            ])->save();
        }

        return $model;
    }
}
