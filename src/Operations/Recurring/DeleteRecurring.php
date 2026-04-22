<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations\Recurring;

use Romanalisoy\PashaBank\Data\OperationResult;
use Romanalisoy\PashaBank\Events\RecurringDeleted;
use Romanalisoy\PashaBank\Models\PashaRecurring;
use Romanalisoy\PashaBank\Operations\Operation;

/**
 * Delete a recurring template — command=x. The bank no longer allows
 * ->charge() calls against this biller_client_id.
 *
 * PDF §4.18.
 */
class DeleteRecurring extends Operation
{
    use RecurringAware;

    /**
     * Shorthand for DeleteRecurring::execute(): the manager invokes it as
     * PashaBank::recurring()->delete($id), so accepting no args and calling
     * this method feels natural. Returns the bank's OperationResult.
     */
    public function __invoke(): OperationResult
    {
        return $this->execute();
    }

    public function execute(): OperationResult
    {
        $billerClientId = $this->requireBillerClientId();

        $response = $this->send([
            'command' => 'x',
            'biller_client_id' => $billerClientId,
        ]);

        $result = OperationResult::fromResponse($response);
        $recurring = $this->findRecurring($billerClientId);

        if ($result->isSuccessful() && $recurring instanceof PashaRecurring) {
            $recurring->markDeleted();
        }

        $this->events->dispatch(new RecurringDeleted(
            billerClientId: $billerClientId,
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
}
