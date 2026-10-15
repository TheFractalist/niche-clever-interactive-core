<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Diagnostics;

use NicheCleverInteractive\Core\AI\Transport\ProviderFailure;

/** One small, non-autoloaded WordPress option holding the latest failure's reason, UTC time and duration. */
final class OptionFailureLog implements FailureLog
{
    public const OPTION_NAME = 'ncli_core_last_failure';

    public function record(string $source, string $reason, int $elapsedSeconds): void
    {
        if (!function_exists('update_option')) {
            return;
        }

        try {
            update_option(self::OPTION_NAME, [
                'source' => $source === 'connection_test' ? 'connection_test' : 'public',
                'reason' => ProviderFailure::isKnown($reason) ? $reason : ProviderFailure::UNEXPECTED_ERROR,
                'at' => gmdate('Y-m-d\TH:i:s\Z'),
                'elapsed' => max(0, min(3600, $elapsedSeconds)),
            ], false);
        } catch (\Throwable $exception) {
            // Diagnostics must never alter the visitor's response.
        }
    }

    public function last(): ?array
    {
        if (!function_exists('get_option')) {
            return null;
        }
        $stored = get_option(self::OPTION_NAME, null);
        if (!is_array($stored) || !is_string($stored['reason'] ?? null) || !is_string($stored['at'] ?? null)) {
            return null;
        }

        return [
            'source' => ($stored['source'] ?? '') === 'connection_test' ? 'connection_test' : 'public',
            'reason' => ProviderFailure::isKnown($stored['reason']) ? $stored['reason'] : ProviderFailure::UNEXPECTED_ERROR,
            'at' => substr($stored['at'], 0, 32),
            'elapsed' => is_int($stored['elapsed'] ?? null) ? max(0, min(3600, $stored['elapsed'])) : 0,
        ];
    }
}
