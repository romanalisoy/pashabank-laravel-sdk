<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Client;

use Romanalisoy\PashaBank\Exceptions\ConfigurationException;

/**
 * Immutable snapshot of a single merchant's configuration, resolved once at
 * lookup time. Passing this into the client makes calls deterministic and
 * side-effect free — no re-reading config() mid-flight.
 */
final readonly class MerchantConfig
{
    public function __construct(
        public string $key,
        public string $merchantId,
        public ?string $terminalId,
        public string $certificateType,
        public string $certificatePath,
        public ?string $certificatePassword,
        public ?string $certificateKeyPath,
        public ?string $certificateCaPath,
        public string $language,
        public string $currency,
        public string $merchantHandlerUrl,
        public string $clientHandlerUrl,
    ) {}

    /**
     * Build a MerchantConfig from the raw config array PashaBankManager
     * keeps in memory. Performs only the validation that would make the
     * client outright unusable — finer field-level checks live on each
     * Operation so error messages can point at the actual call site.
     *
     * @param  array<string, mixed>  $merchant
     * @param  array{merchant_handler: string|null, client_handler: string|null}  $endpoints
     */
    public static function fromArray(string $key, array $merchant, array $endpoints): self
    {
        $merchantId = (string) ($merchant['merchant_id'] ?? '');
        if ($merchantId === '') {
            throw ConfigurationException::missingMerchantId($key);
        }

        $certificate = $merchant['certificate'] ?? [];
        $certPath = (string) ($certificate['path'] ?? '');
        if ($certPath === '') {
            throw ConfigurationException::missingCertificate($key);
        }

        $certType = strtolower((string) ($certificate['type'] ?? 'pkcs12'));
        if (! in_array($certType, ['pkcs12', 'pem'], true)) {
            throw ConfigurationException::unsupportedCertificateType($certType);
        }

        if (! is_readable($certPath)) {
            throw ConfigurationException::certificateNotReadable($certPath);
        }

        if (empty($endpoints['merchant_handler'])) {
            throw ConfigurationException::missingEndpoint('current', 'merchant_handler');
        }

        if (empty($endpoints['client_handler'])) {
            throw ConfigurationException::missingEndpoint('current', 'client_handler');
        }

        return new self(
            key: $key,
            merchantId: $merchantId,
            terminalId: isset($merchant['terminal_id']) ? (string) $merchant['terminal_id'] : null,
            certificateType: $certType,
            certificatePath: $certPath,
            certificatePassword: isset($certificate['password']) ? (string) $certificate['password'] : null,
            certificateKeyPath: isset($certificate['key_path']) && $certificate['key_path'] !== ''
                ? (string) $certificate['key_path']
                : null,
            certificateCaPath: isset($certificate['ca_path']) && $certificate['ca_path'] !== ''
                ? (string) $certificate['ca_path']
                : null,
            language: (string) ($merchant['language'] ?? 'az'),
            currency: (string) ($merchant['currency'] ?? 'AZN'),
            merchantHandlerUrl: (string) $endpoints['merchant_handler'],
            clientHandlerUrl: (string) $endpoints['client_handler'],
        );
    }
}
