<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations;

use Romanalisoy\PashaBank\Data\OperationResult;
use Romanalisoy\PashaBank\Models\PashaTransaction;
use Romanalisoy\PashaBank\Support\Amount;

/**
 * Dual Message (DMS) Transaction Advice — command=t. Captures a DMS
 * authorization previously obtained with DmsAuthorization.
 *
 * PDF §4.6.
 */
class DmsCompletion extends Operation
{
    public function execute(): OperationResult
    {
        $transactionId = $this->requireTransactionId();
        $amountMinor = $this->requireAmount();
        $currency = $this->requireCurrency();

        $response = $this->send(array_merge($this->parameters(), [
            'command' => 't',
            'trans_id' => $transactionId,
            'amount' => Amount::toBankString($amountMinor),
            'currency' => $currency,
            'msg_type' => 'DMS',
        ]));

        $result = OperationResult::fromResponse($response);

        $this->updateParentTransaction($transactionId, $result);

        return $result;
    }

    protected function updateParentTransaction(string $transactionId, OperationResult $result): void
    {
        if (! $this->persistenceEnabled()) {
            return;
        }

        /** @var class-string<PashaTransaction> $class */
        $class = $this->transactionModelClass();

        $model = $class::query()->where('transaction_id', $transactionId)->first();
        if (! $model instanceof PashaTransaction) {
            return;
        }

        if ($result->isSuccessful()) {
            $model->markOk(
                approvalCode: $result->approvalCode,
                rrn: $result->rrn,
                cardMask: null,
                resultCode: $result->resultCode,
                threeDs: null,
            );
        } else {
            $model->markFailed($result->resultCode);
        }
    }
}
