<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Romanalisoy\PashaBank\Data\TransactionStatus;
use Romanalisoy\PashaBank\Exceptions\PashaBankException;
use Romanalisoy\PashaBank\Models\PashaTransaction;
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

        // In split-route mode the route file injects the bank's URL choice
        // ('success' or 'failure') as a route default. It is only a hint —
        // we still verify with command=c — but it lets us fail fast if the
        // bank chose the failure URL with no body error.
        $outcomeHint = (string) ($request->route()?->defaults['_pashabank_outcome_hint'] ?? '');

        try {
            $status = $this->pasha->completion($transactionId)->get();
        } catch (PashaBankException $e) {
            return $this->failure(
                $transactionId,
                $bankError !== '' ? $bankError : $e->getMessage()
            );
        }

        $isSuccess = $status->isSuccessful();

        // If the bank's URL choice disagrees with command=c, trust
        // command=c (server-side authoritative) but log the conflict so
        // operators can investigate misconfiguration.
        if ($outcomeHint === 'failure' && $isSuccess) {
            $isSuccess = false;
        }

        if ($isSuccess) {
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

        $url = $this->resolveReturnUrl(
            $transactionId,
            metaKey: 'return_success_url',
            configKey: 'pashabank.callback.success_url',
            fallback: '/payment/success',
        );

        return new RedirectResponse($this->appendTransactionId($url, $transactionId));
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

        $url = $this->resolveReturnUrl(
            $transactionId,
            metaKey: 'return_failure_url',
            configKey: 'pashabank.callback.failure_url',
            fallback: '/payment/failure',
        );

        return new RedirectResponse($this->appendTransactionId($url, $transactionId));
    }

    /**
     * Per-payment redirect targets win over the static config defaults.
     * The Operation builder writes them to the transaction's meta column
     * via ->returnUrls(...), so each payment can land on a unique page —
     * for example a per-ad receipt URL like /ads/42 instead of a generic
     * /payment/success.
     */
    private function resolveReturnUrl(?string $transactionId, string $metaKey, string $configKey, string $fallback): string
    {
        if ($transactionId !== null) {
            $meta = $this->lookupTransactionMeta($transactionId);
            if (isset($meta[$metaKey]) && is_string($meta[$metaKey]) && $meta[$metaKey] !== '') {
                return $meta[$metaKey];
            }
        }

        return (string) $this->config->get($configKey, $fallback);
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupTransactionMeta(string $transactionId): array
    {
        if (! $this->config->get('pashabank.persistence.enabled', true)) {
            return [];
        }

        /** @var class-string<PashaTransaction> $class */
        $class = $this->config->get('pashabank.persistence.models.transaction', PashaTransaction::class);

        $model = $class::query()->where('transaction_id', $transactionId)->first();

        if (! $model instanceof PashaTransaction) {
            return [];
        }

        /** @var array<string, mixed>|null $meta */
        $meta = $model->meta;

        return $meta ?? [];
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
