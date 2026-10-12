<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

final class PublicUrlPolicy
{
    public static function allows(string $url): bool
    {
        $parts = wp_parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return strlen($url) <= 2048;
    }
}
