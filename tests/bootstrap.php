<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
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
