<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * Returned from any operation that asks the bank to register a new payment
 * (SMS / DMS / recurring registration). Carries the bank's transaction
 * identifier and the full redirect URL the client's browser must visit.
 */
final readonly class PaymentRegistration
{
    public function __construct(
        public string $transactionId,
        public string $redirectUrl,
        public ?Model $transaction = null,
    ) {}

    /**
     * Serialise for JSON APIs / mobile clients. The persisted model is
     * intentionally omitted — consumers shouldn't round-trip DB rows.
     *
     * @return array{transaction_id: string, redirect_url: string}
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'redirect_url' => $this->redirectUrl,
        ];
    }
}
