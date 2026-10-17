<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * A title and public https address the site owner has approved for display beneath an answer.
 * Core validates it again before it reaches a visitor, so a malformed value can never be shown.
 */
final class ApprovedLinkV1
{
    public const MAX_TITLE = 160;
    public const MAX_URL = 2000;

    private string $title;
    private string $url;

    public function __construct(string $title, string $url)
    {
        $title = trim($title);
        if ($title === '' || strlen($title) > self::MAX_TITLE || preg_match('/[\x00-\x1F\x7F]/', $title) === 1 || $title !== strip_tags($title) // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Only detects markup in a title (it is rejected, never output); the stricter WordPress function is not loaded in pure tests.
        ) {
            throw new InvalidArgumentException('A link title must be short plain text.');
        }
        if (!self::isPublicHttpsUrl($url)) {
            throw new InvalidArgumentException('A link must be a public https address.');
        }

        $this->title = $title;
        $this->url = $url;
    }

    public static function isPublicHttpsUrl(string $url): bool
    {
        if ($url === '' || strlen($url) > self::MAX_URL || preg_match('/[\x00-\x20\x7F"<>\\\\^`{|}]/', $url) === 1) {
            return false;
        }

        $parts = function_exists('wp_parse_url') ? wp_parse_url($url) : parse_url($url); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Fallback when WordPress is not loaded (pure tests).
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) !== 1) {
            return false;
        }
        // Public names only: no numeric (IP) hosts and no local or internal suffixes.
        if (preg_match('/^[0-9.]+$/', $host) === 1 || preg_match('/\.(local|localhost|internal|lan|home|corp|intranet)$/', $host) === 1) {
            return false;
        }

        return true;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function url(): string
    {
        return $this->url;
    }
}
