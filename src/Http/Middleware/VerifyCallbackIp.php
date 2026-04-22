<?php

declare(strict_types=1);

namespace Romanalisoy\PashaBank\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the bank callback route unless the caller's IP address matches
 * one of the allowlisted entries. The allowlist is empty by default
 * (everyone allowed) — set PASHABANK_CALLBACK_IPS in production once you
 * have the bank's static IPs.
 */
final class VerifyCallbackIp
{
    public function __construct(private readonly ConfigRepository $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $allowlist */
        $allowlist = array_filter(array_map(
            'trim',
            (array) $this->config->get('pashabank.callback.ip_allowlist', [])
        ));

        if ($allowlist === []) {
            return $next($request);
        }

        $incoming = $request->ip();
        if ($incoming === null) {
            abort(403, 'Callback IP could not be determined.');
        }

        foreach ($allowlist as $allowed) {
            if ($this->matches($incoming, $allowed)) {
                return $next($request);
            }
        }

        abort(403, 'Callback IP is not on the allowlist.');
    }

    private function matches(string $incoming, string $allowed): bool
    {
        if ($incoming === $allowed) {
            return true;
        }

        if (! str_contains($allowed, '/')) {
            return false;
        }

        // CIDR support for IPv4.
        [$subnet, $maskLength] = explode('/', $allowed, 2);
        $maskLength = (int) $maskLength;

        $incomingLong = ip2long($incoming);
        $subnetLong = ip2long($subnet);

        if ($incomingLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - $maskLength);
        $subnetLong &= $mask;

        return ($incomingLong & $mask) === $subnetLong;
    }
}
