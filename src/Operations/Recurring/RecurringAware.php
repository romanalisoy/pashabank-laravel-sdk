<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Operations\Recurring;

use Romanalisoy\PashaBank\Exceptions\ValidationException;

/**
 * Mixin for operations that work against a recurring template identified
 * by biller_client_id. Keeps the validation rules (28-char limit, MMYY
 * expiry format) in one place.
 */
trait RecurringAware
{
    protected ?string $billerClientId = null;

    protected ?string $expiry = null;

    protected bool $overwriteTemplate = false;

    public function billerClientId(string $id): static
    {
        if (strlen($id) > 28) {
            throw ValidationException::billerClientIdTooLong(strlen($id));
        }

        $this->billerClientId = $id;

        return $this;
    }

    public function expiry(string $mmYY): static
    {
        if (preg_match('/^(0[1-9]|1[0-2])\d{2}$/', $mmYY) !== 1) {
            throw ValidationException::invalidExpiry($mmYY);
        }

        $this->expiry = $mmYY;

        return $this;
    }

    public function overwrite(bool $flag = true): static
    {
        $this->overwriteTemplate = $flag;

        return $this;
    }

    protected function requireBillerClientId(): string
    {
        if ($this->billerClientId === null || $this->billerClientId === '') {
            throw ValidationException::missingField('biller_client_id');
        }

        return $this->billerClientId;
    }

    protected function requireExpiry(): string
    {
        if ($this->expiry === null || $this->expiry === '') {
            throw ValidationException::missingField('perspayee_expiry');
        }

        return $this->expiry;
    }
}
