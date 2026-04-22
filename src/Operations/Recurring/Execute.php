<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations\Recurring;

use Romanalisoy\PashaBank\Data\OperationResult;
use Romanalisoy\PashaBank\Events\RecurringExecuted;
use Romanalisoy\PashaBank\Models\PashaRecurring;
use Romanalisoy\PashaBank\Models\PashaTransaction;
use Romanalisoy\PashaBank\Operations\Operation;
use Romanalisoy\PashaBank\Support\Amount;

/**
 * Execute a previously registered recurring payment — command=e. No
 * client interaction: the bank charges the stored card immediately and
 * returns a decision.
 *
 * PDF §4.17. Domestic cards only.
 */
class Execute extends Operation
{
    use RecurringAware;

    public function charge(): OperationResult
    {
        $amountMinor = $this->requireAmount();
        $currency = $this->requireCurrency();
        $billerClientId = $this->requireBillerClientId();

        $response = $this->send(array_merge($this->parameters(), [
            'command' => 'e',
            'amount' => Amount::toBankString($amountMinor),
            'currency' => $currency,
            'biller_client_id' => $billerClientId,
        ]));

        $result = OperationResult::fromResponse($response);
        $transactionId = $response->get('TRANSACTION_ID') ?? '';

        $recurring = $this->findRecurring($billerClientId);
        $transaction = $this->recordExecution($transactionId, $amountMinor, $currency, $result, $recurring);

        $this->events->dispatch(new RecurringExecuted(
            billerClientId: $billerClientId,
            amountMinor: $amountMinor,
            currency: $currency,
            result: $result,
            recurring: $recurring,
        ));

        return $result;
    }

    protected function findRecurring(string $billerClientId): ?PashaRecurring
    {
        if (! $this->persistence['enabled']) {
            return null;
        }

        /** @var class-string<PashaRecurring> $class */
        $class = $this->recurringModelClass();

        /** @var PashaRecurring|null $model */
        $model = $class::query()->where('biller_client_id', $billerClientId)->first();

        return $model;
    }

    protected function recordExecution(
        string $transactionId,
        int $amountMinor,
        string $currency,
        OperationResult $result,
        ?PashaRecurring $recurring,
    ): ?PashaTransaction {
        if (! $this->persistenceEnabled() || $transactionId === '') {
            return null;
        }

        /** @var class-string<PashaTransaction> $class */
        $class = $this->transactionModelClass();

        /** @var PashaTransaction $model */
        $model = new $class;
        $model->fill([
            'merchant_key' => $this->merchant->key,
            'transaction_id' => $transactionId,
            'command' => 'e',
            'status' => $result->isSuccessful() ? PashaTransaction::STATUS_OK : PashaTransaction::STATUS_FAILED,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'result_code' => $result->resultCode,
            'approval_code' => $result->approvalCode,
            'rrn' => $result->rrn,
            'description' => $this->description,
            'recurring_id' => $recurring?->getKey(),
        ]);
        $model->save();

        return $model;
    }
}
