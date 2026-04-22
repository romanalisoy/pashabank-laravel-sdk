<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Exceptions;

final class ConfigurationException extends PashaBankException
{
    public static function missingMerchant(string $key): self
    {
        return new self(sprintf(
            'PASHA Bank merchant "%s" is not configured. Add it under "merchants" in config/pashabank.php.',
            $key
        ));
    }

    public static function missingMerchantId(string $key): self
    {
        return new self(sprintf(
            'PASHA Bank merchant "%s" has no merchant_id. Set PASHABANK_MERCHANT_ID or update the config.',
            $key
        ));
    }

    public static function missingCertificate(string $key): self
    {
        return new self(sprintf(
            'PASHA Bank merchant "%s" has no certificate configured. Set PASHABANK_CERT_PATH.',
            $key
        ));
    }

    public static function certificateNotReadable(string $path): self
    {
        return new self(sprintf('PASHA Bank certificate is not readable at "%s".', $path));
    }

    public static function unsupportedCertificateType(string $type): self
    {
        return new self(sprintf(
            'Unsupported certificate type "%s". Use "pkcs12" or "pem".',
            $type
        ));
    }

    public static function missingEndpoint(string $environment, string $handler): self
    {
        return new self(sprintf(
            'PASHA Bank endpoint "%s" for environment "%s" is not configured.',
            $handler,
            $environment
        ));
    }
}
