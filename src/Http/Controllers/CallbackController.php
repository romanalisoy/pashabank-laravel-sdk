<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Romanalisoy\PashaBank\Data\TransactionStatus;
use Romanalisoy\PashaBank\Exceptions\PashaBankException;
use Romanalisoy\PashaBank\PashaBankManager;

/**
 * Default handler for the bank's RETURN_OK_URL POST. Calls command=c to
 * finalize the payment, which dispatches PaymentCompleted/PaymentFailed,
 * then either redirects the browser to the configured URL or returns
 * JSON (SPA / mobile flow).
 */
final class CallbackController
{
    public function __construct(
        private readonly PashaBankManager $pasha,
        private readonly ConfigRepository $config,
    ) {}

    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $transactionId = (string) ($request->input('trans_id') ?? '');
        if ($transactionId === '') {
            return $this->failure(null, 'Missing trans_id in callback payload.');
        }

        try {
            $status = $this->pasha->completion($transactionId)->get();
        } catch (PashaBankException $e) {
            return $this->failure($transactionId, $e->getMessage());
        }

        return $status->isSuccessful()
            ? $this->success($transactionId, $status)
            : $this->failure($transactionId, $status->description(), $status);
    }

    private function success(string $transactionId, TransactionStatus $status): RedirectResponse|JsonResponse
    {
        if ($this->wantsJson()) {
            return new JsonResponse([
                'status' => 'success',
                'transaction_id' => $transactionId,
                'payment' => $status->toArray(),
            ]);
        }

        $url = $this->appendTransactionId(
            (string) $this->config->get('pashabank.callback.success_url', '/payment/success'),
            $transactionId
        );

        return new RedirectResponse($url);
    }

    private function failure(?string $transactionId, string $reason, ?TransactionStatus $status = null): RedirectResponse|JsonResponse
    {
        if ($this->wantsJson()) {
            return new JsonResponse([
                'status' => 'failure',
                'transaction_id' => $transactionId,
                'reason' => $reason,
                'payment' => $status?->toArray(),
            ], 422);
        }

        $url = $this->appendTransactionId(
            (string) $this->config->get('pashabank.callback.failure_url', '/payment/failure'),
            $transactionId
        );

        return new RedirectResponse($url);
    }

    private function wantsJson(): bool
    {
        return strtolower((string) $this->config->get('pashabank.callback.response', 'redirect')) === 'json';
    }

    private function appendTransactionId(string $url, ?string $transactionId): string
    {
        if ($transactionId === null) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'trans_id='.urlencode($transactionId);
    }
}
