<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Rest;

/**
 * The deliberately small, normalised subset of an HTTP request that Core needs after REST
 * dispatch. It prevents arbitrary server values from crossing into the chat or extension seams.
 */
final class RequestEnvironment
{
    public const USER_AGENT_BYTES = 512;
    /** @var array<string, string> */
    private array $clientScopeServer;
    private ?string $userAgent;
    private string $accept;

    /** @param array<string, string> $clientScopeServer Only REMOTE_ADDR and HTTP_X_FORWARDED_FOR. */
    private function __construct(array $clientScopeServer, ?string $userAgent, string $accept)
    {
        $this->clientScopeServer = $clientScopeServer;
        $this->userAgent = $userAgent;
        $this->accept = $accept;
    }

    /** @param array<string, mixed> $server */
    public static function fromServer(array $server): self
    {
        $scope = [];
        foreach (['REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR'] as $key) {
            if (is_string($server[$key] ?? null)) {
                $scope[$key] = $server[$key];
            }
        }

        return new self($scope, self::normaliseUserAgent($server['HTTP_USER_AGENT'] ?? null), self::header($server['HTTP_ACCEPT'] ?? null));
    }

    public static function empty(): self
    {
        return new self([], null, '');
    }

    /** @return array<string, string> */
    public function clientScopeServer(): array
    {
        return $this->clientScopeServer;
    }

    /** A validated, control-free value for the optional extension recorder seam. */
    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function acceptsEventStream(): bool
    {
        return stripos($this->accept, 'text/event-stream') !== false;
    }

    /** @param mixed $value */
    private static function normaliseUserAgent($value): ?string
    {
        if (!is_string($value) || $value === '' || preg_match('//u', $value) !== 1) {
            return null;
        }

        // Headers are not form values, so deliberately do not wp_unslash(). sanitize_text_field()
        // is used where WordPress is loaded; the fallback preserves the same security properties in
        // pure tests without pretending to parse an arbitrary header grammar.
        $clean = function_exists('sanitize_text_field') ? sanitize_text_field($value) : preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        if (!is_string($clean)) {
            return null;
        }
        $clean = trim($clean);
        if ($clean === '') {
            return null;
        }

        $clean = substr($clean, 0, self::USER_AGENT_BYTES);
        while ($clean !== '' && preg_match('//u', $clean) !== 1) {
            $clean = substr($clean, 0, -1);
        }

        return $clean === '' || preg_match('/[\x00-\x1F\x7F]/', $clean) === 1 ? null : $clean;
    }

    /** @param mixed $value */
    private static function header($value): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            return '';
        }

        return substr((string) preg_replace('/[\x00-\x1F\x7F]/', '', $value), 0, 512);
    }
}
