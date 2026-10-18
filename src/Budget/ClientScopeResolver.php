<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

final class ClientScopeResolver
{
    private string $secretConstant;
    private string $trustedProxyConstant;
    /** @var (callable(): ?string)|null */
    private $saltProvider;

    /**
     * @param (callable(): ?string)|null $saltProvider Test seam; defaults to the WordPress authentication salt.
     */
    public function __construct(string $secretConstant = 'NCLI_CORE_SCOPE_SECRET', string $trustedProxyConstant = 'NCLI_CORE_TRUSTED_PROXY_CIDRS', ?callable $saltProvider = null)
    {
        $this->secretConstant = $secretConstant;
        $this->trustedProxyConstant = $trustedProxyConstant;
        $this->saltProvider = $saltProvider;
    }

    /**
     * Whether a server-side secret is available, so a visitor scope can be derived.
     * No setup is required on a normal WordPress site (see secret()).
     */
    public function isAvailable(): bool
    {
        return $this->secret() !== null;
    }

    /**
     * An explicitly configured NCLI_CORE_SCOPE_SECRET always wins. Otherwise the secret
     * is derived from the site's own WordPress authentication salt, which lives in
     * wp-config.php, never in the database, and is domain-separated so it is not the
     * salt itself. Rotating the salts only resets visitors' daily counts.
     */
    private function secret(): ?string
    {
        if (defined($this->secretConstant) && is_string(constant($this->secretConstant)) && trim((string) constant($this->secretConstant)) !== '') {
            return trim((string) constant($this->secretConstant));
        }

        $salt = null;
        if ($this->saltProvider !== null) {
            $salt = ($this->saltProvider)();
        } elseif (function_exists('wp_salt')) {
            $salt = wp_salt('auth');
        }
        if (!is_string($salt) || trim($salt) === '') {
            return null;
        }

        return hash_hmac('sha256', 'ncli-core-visitor-scope-secret', $salt);
    }

    /**
     * The visitor's network address under the same trusted-proxy rule as the daily allowance:
     * X-Forwarded-For is read only when REMOTE_ADDR is inside the configured proxy allowlist.
     *
     * @param array<string, mixed> $server
     */
    public function clientAddress(array $server): ?string
    {
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

        return $client;
    }

    /** @param array<string, mixed> $server */
    public function hash(array $server): ?string
    {
        $secret = $this->secret();
        $client = $secret === null ? null : $this->clientAddress($server);
        if ($client === null) {
            return null;
        }

        return hash_hmac('sha256', 'visitor:' . $client, $secret);
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
