<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

/**
 * Normalises request primitives at the WordPress boundary before handlers validate them.
 * Every method returns a scalar suitable for the caller's stricter domain validation.
 */
final class AdminRequest
{
    public static function postText(string $key): string
    {
        return self::postOptionalText($key) ?? '';
    }

    public static function postOptionalText(string $key): ?string
    {
        if (!array_key_exists($key, $_POST) || !is_string($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies its action nonce before changing state.
            return null;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The shared text() helper immediately unslashes and field-sanitises this scalar before it can be returned.
        return self::text($_POST[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The caller verifies its action nonce; text() immediately unslashes and sanitises this scalar.
    }

    public static function postTextarea(string $key): string
    {
        return self::postOptionalTextarea($key) ?? '';
    }

    public static function postOptionalTextarea(string $key): ?string
    {
        if (!array_key_exists($key, $_POST) || !is_string($_POST[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller verifies its action nonce before changing state.
            return null;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- This scalar is unslashed on the next line before bounded validation.
        $value = self::unslash($_POST[$key]); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The caller verifies its action nonce; this scalar is unslashed before bounded validation.

        return function_exists('sanitize_textarea_field') ? sanitize_textarea_field($value) : self::fallbackText($value);
    }

    public static function postCheckbox(string $key): bool
    {
        return self::postText($key) === '1';
    }

    public static function postPositiveInt(string $key): ?int
    {
        $value = self::postOptionalText($key);

        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    public static function postList(string $key): array
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Each accepted list item is unslashed and text-sanitised below.
        $value = $_POST[$key] ?? []; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The caller verifies its action nonce; each accepted list item is unslashed and sanitised below.
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $items[] = self::text($item);
            }
        }

        return $items;
    }

    public static function queryText(string $key): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- The shared text() helper immediately unslashes and field-sanitises this read-only scalar.
        return self::text($_GET[$key] ?? null); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only navigation value; text() immediately unslashes and sanitises this scalar.
    }

    public static function queryPositiveInt(string $key, int $default = 1): int
    {
        $value = self::queryText($key);

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
    }

    private static function text($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = self::unslash($value);

        return function_exists('sanitize_text_field') ? sanitize_text_field($value) : self::fallbackText($value);
    }

    private static function unslash(string $value): string
    {
        return function_exists('wp_unslash') ? wp_unslash($value) : stripslashes($value);
    }

    private static function fallbackText(string $value): string
    {
        return trim((string) preg_replace('/<[^>]*>/', '', $value));
    }
}
