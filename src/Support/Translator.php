<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Support;

/**
 * Keeps fixed user-facing strings translatable while preserving pure PHP tests.
 */
final class Translator
{
    public const TEXT_DOMAIN = 'niche-clever-interactive';

    public static function text(string $text): string
    {
        if (!function_exists('__')) {
            return $text;
        }

        // The fixed English source text remains the translation key. Indirection
        // keeps this narrow adapter usable by the plugin's pure-PHP contract tests.
        $translate = '__';
        return (string) $translate($text, self::TEXT_DOMAIN);
    }
}
