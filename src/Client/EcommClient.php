<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Client;

use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Romanalisoy\PashaBank\Exceptions\ConnectionException;
use Romanalisoy\PashaBank\Support\CardMask;
use Throwable;

/**
 * Low-level HTTP client. Wraps Laravel's Http factory so tests can use
 * Http::fake(), while enforcing the mTLS/PKCS#12 handshake the bank
 * requires. Every call is a POST to /MerchantHandler with a
 * application/x-www-form-urlencoded body.
 */
final class EcommClient
{
    public function __construct(
        private readonly HttpFactory $http,
        /** @var array{timeout: int, connect_timeout: int, verify_ssl: bool, verify_host: bool, return_transfer: bool, tls_version: string} */
        private readonly array $httpConfig,
        /** @var array{enabled: bool, channel: string, mask_card_numbers: bool} */
        private readonly array $loggingConfig,
    ) {}

    /**
     * Send a command to /MerchantHandler and return the parsed response.
     *
     * @param  array<string, scalar|null>  $parameters
     */
    public function send(MerchantConfig $merchant, array $parameters): Response
    {
        $parameters = array_filter(
            $parameters,
            static fn ($value): bool => $value !== null && $value !== '',
        );

        $this->logRequest($merchant, $parameters);

        try {
            $response = $this->http
                ->asForm()
                ->timeout($this->httpConfig['timeout'])
                ->connectTimeout($this->httpConfig['connect_timeout'])
                ->withOptions($this->buildCurlOptions($merchant))
                ->post($merchant->merchantHandlerUrl, $parameters);
        } catch (HttpConnectionException $e) {
            throw ConnectionException::fromTransport($e->getMessage(), $e);
        } catch (Throwable $e) {
            throw ConnectionException::fromTransport($e->getMessage(), $e);
        }

        $body = $response->body();
        $parsed = Response::parse($body);

        $this->logResponse($parsed);

        return $parsed->throwIfErrored();
    }

    /**
     * Build the Guzzle option set for a single merchant. PKCS#12 keystores
     * are passed as the `cert` option alongside their password; separate
     * PEM cert + key files use `cert` + `ssl_key`.
     *
     * The PDF mandates these curl options:
     *   CURLOPT_SSL_VERIFYPEER, CURLOPT_SSL_VERIFYHOST, CURLOPT_RETURNTRANSFER.
     * They are exposed as separate config flags so a single .env switch can
     * relax peer-only verification (debugging) without disabling host-name
     * checks, and the return-transfer flag is set explicitly even though
     * Laravel's Http facade already requires it.
     *
     * @return array<string, mixed>
     */
    private function buildCurlOptions(MerchantConfig $merchant): array
    {
        $options = [
            'verify' => $this->resolveVerify($merchant),
            'curl' => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_SSL_VERIFYPEER => $this->httpConfig['verify_ssl'],
                // VERIFYHOST has 3 meaningful states in curl: 0 (off), 2 (full).
                // Guzzle/curl treat any truthy non-zero as "verify hostname".
                CURLOPT_SSL_VERIFYHOST => $this->httpConfig['verify_host'] ? 2 : 0,
                CURLOPT_RETURNTRANSFER => $this->httpConfig['return_transfer'],
            ],
        ];

        if ($merchant->certificateType === 'pkcs12') {
            $options['cert'] = $merchant->certificatePassword !== null
                ? [$merchant->certificatePath, $merchant->certificatePassword]
                : $merchant->certificatePath;
            $options['curl'][CURLOPT_SSLCERTTYPE] = 'P12';
        } else {
            $options['cert'] = $merchant->certificatePath;
            if ($merchant->certificateKeyPath !== null) {
                $options['ssl_key'] = $merchant->certificatePassword !== null
                    ? [$merchant->certificateKeyPath, $merchant->certificatePassword]
                    : $merchant->certificateKeyPath;
            }
        }

        return $options;
    }

    /**
     * Translate the two boolean flags (verify_ssl, verify_host) into the
     * single `verify` option Guzzle understands. Guzzle's option does not
     * distinguish peer vs. host — so when only one is disabled we have to
     * disable the high-level option entirely and rely on the curl-level
     * options injected above to keep the other check active.
     */
    private function resolveVerify(MerchantConfig $merchant): bool|string
    {
        $peer = $this->httpConfig['verify_ssl'];
        $host = $this->httpConfig['verify_host'];

        if (! $peer || ! $host) {
            return false;
        }

        return $merchant->certificateCaPath ?? true;
    }

    /**
     * @param  array<string, scalar|null>  $parameters
     */
    private function logRequest(MerchantConfig $merchant, array $parameters): void
    {
        if (! $this->loggingConfig['enabled']) {
            return;
        }

        $safe = $this->loggingConfig['mask_card_numbers']
            ? CardMask::scrub($parameters)
            : $parameters;

        $this->channel()->info('[pashabank] → MerchantHandler', [
            'merchant' => $merchant->key,
            'command' => $parameters['command'] ?? null,
            'url' => $merchant->merchantHandlerUrl,
            'parameters' => $safe,
        ]);
    }

    private function logResponse(Response $response): void
    {
        if (! $this->loggingConfig['enabled']) {
            return;
        }

        $fields = $response->all();
        if ($this->loggingConfig['mask_card_numbers']) {
            if (isset($fields['CARD_NUMBER'])) {
                $fields['CARD_NUMBER'] = CardMask::mask($fields['CARD_NUMBER']);
            }
            if (isset($fields['CARD_NUMBER2'])) {
                $fields['CARD_NUMBER2'] = CardMask::mask($fields['CARD_NUMBER2']);
            }
        }

        $this->channel()->info('[pashabank] ← MerchantHandler', ['fields' => $fields]);
    }

    private function channel(): LoggerInterface
    {
        $channel = $this->loggingConfig['channel'];

        // Fall back to the global logger when the named channel is unknown.
        try {
            return Log::channel($channel);
        } catch (Throwable) {
            return Log::getLogger();
        }
    }
}
