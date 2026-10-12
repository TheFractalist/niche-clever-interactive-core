<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

final class ClientScopeResolver
{
    private string $secretConstant;
    private string $trustedProxyConstant;

    public function __construct(string $secretConstant = 'NCLI_CORE_SCOPE_SECRET', string $trustedProxyConstant = 'NCLI_CORE_TRUSTED_PROXY_CIDRS')
    {
        $this->secretConstant = $secretConstant;
        $this->trustedProxyConstant = $trustedProxyConstant;
    }

    /** @param array<string, mixed> $server */
    public function hash(array $server): ?string
    {
        if (!defined($this->secretConstant) || !is_string(constant($this->secretConstant)) || trim((string) constant($this->secretConstant)) === '') {
            return null;
        }

        $remote = $server['REMOTE_ADDR'] ?? null;
        if (!is_string($remote) || filter_var($remote, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $client = $remote;
        if ($this->isTrustedProxy($remote) && is_string($server['HTTP_X_FORWARDED_FOR'] ?? null)) {
            $candidate = trim(explode(',', $server['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                $client = $candidate;
            }
        }

        return hash_hmac('sha256', 'visitor:' . $client, trim((string) constant($this->secretConstant)));
    }

    private function isTrustedProxy(string $address): bool
    {
        if (!defined($this->trustedProxyConstant) || !is_string(constant($this->trustedProxyConstant))) {
            return false;
        }

        foreach (array_filter(array_map('trim', explode(',', (string) constant($this->trustedProxyConstant)))) as $cidr) {
            if ($this->matchesCidr($address, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCidr(string $address, string $cidr): bool
    {
        [$network, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($bits === null || filter_var($network, FILTER_VALIDATE_IP) === false || !ctype_digit($bits)) {
            return false;
        }
        $addressBytes = @inet_pton($address);
        $networkBytes = @inet_pton($network);
        $length = $addressBytes === false ? 0 : strlen($addressBytes);
        if ($addressBytes === false || $networkBytes === false || $length !== strlen($networkBytes) || (int) $bits < 0 || (int) $bits > $length * 8) {
            return false;
        }
        $whole = intdiv((int) $bits, 8);
        $remainder = (int) $bits % 8;
        if ($whole > 0 && substr($addressBytes, 0, $whole) !== substr($networkBytes, 0, $whole)) {
            return false;
        }
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xff << (8 - $remainder)) & 0xff;
        return (ord($addressBytes[$whole]) & $mask) === (ord($networkBytes[$whole]) & $mask);
    }
}
