<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

if (!function_exists('wp_salt')) {
    /** Pure-test stand-in for WordPress's per-site salt; fixed so derived scopes are reproducible. */
    function wp_salt(string $scheme = 'auth'): string
    {
        return 'pure-test-site-salt-' . $scheme;
    }
}

if (!function_exists('wp_parse_url')) {
    /**
     * Minimal pure-test stand-in for the WordPress runtime helper.
     *
     * @return array<string, mixed>|false
     */
    function wp_parse_url(string $url, int $component = -1)
    {
        return parse_url($url, $component);
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('wp_kses')) {
    function wp_kses(string $content, array $allowed_html = []): string
    {
        return $content;
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql(string $value): string
    {
        return $value;
    }
}
