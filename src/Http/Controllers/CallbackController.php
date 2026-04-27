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

        // The bank may inline an error directly in the redirect body, e.g.
        //   trans_id=...&error=card+authentication+error%3A+Card+not+authenticated
        // Capture it so the failure response can carry the bank's own
        // wording. We still call command=c afterwards so server-side state
        // is authoritative — never trust a browser-mediated POST alone.
        $bankError = (string) ($request->input('error') ?? '');

        try {
            $status = $this->pasha->completion($transactionId)->get();
        } catch (PashaBankException $e) {
            return $this->failure(
                $transactionId,
                $bankError !== '' ? $bankError : $e->getMessage()
            );
        }

        if ($status->isSuccessful()) {
            return $this->success($transactionId, $status);
        }

        // Prefer the bank's verbatim error if it was supplied; otherwise
        // fall back to the human description of the result code.
        $reason = $bankError !== '' ? $bankError : $status->description();

        return $this->failure($transactionId, $reason, $status);
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
