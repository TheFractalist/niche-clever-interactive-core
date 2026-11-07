<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Configuration\Readiness;

/**
 * Plain-language guidance shared by the default profile's Setup screen and a named profile's
 * workspace: what the vector-store step is still waiting for, and when to send the owner back
 * to run a new connection test.
 */
final class SetupGuidance
{
    public static function vectorStoreTitle(): string
    {
        return __('Choose your vector store and corpus version', 'niche-clever-interactive');
    }

    /** Names exactly which of the two saved values is missing. */
    public static function vectorStoreMessage(bool $hasReference, bool $hasCorpusVersion, bool $namedProfile = false): string
    {
        if ($hasReference && $hasCorpusVersion) {
            return $namedProfile
                ? __('A vector-store reference and corpus version are saved for this profile.', 'niche-clever-interactive')
                : __('A vector-store reference and corpus version are saved.', 'niche-clever-interactive');
        }
        if ($hasReference) {
            return __('The corpus version is missing. Save it in Profile.', 'niche-clever-interactive');
        }
        if ($hasCorpusVersion) {
            return __('The vector-store reference is missing. Save it in AI & Corpus.', 'niche-clever-interactive');
        }

        return __('Save the vector-store reference in AI & Corpus and the corpus version in Profile.', 'niche-clever-interactive');
    }

    /**
     * Every other requirement is met, so the only thing keeping visitors out is a passing test:
     * either settings changed since the last one, or none has passed.
     */
    public static function needsRetest(string $readinessState): bool
    {
        return in_array($readinessState, [Readiness::STALE_CONNECTION_TEST, Readiness::MISSING_CONNECTION_TEST], true);
    }

    /**
     * Draws a warning that sends the owner back to Setup to run a new connection test. Draws
     * nothing when no test is needed, so it is safe to call on every administrator tab.
     *
     * @param string $lastStatus The stored connection-test status: `never`, `passed` or `failed`.
     */
    public static function renderRetestNotice(string $readinessState, string $lastStatus, string $setupUrl, string $diagnosticsUrl): void
    {
        if (!self::needsRetest($readinessState)) {
            return;
        }
        $failed = $readinessState === Readiness::MISSING_CONNECTION_TEST && $lastStatus === 'failed';
        if ($readinessState === Readiness::STALE_CONNECTION_TEST) {
            $message = __('A setting that affects answers has changed since the last connection test, so visitors see the unavailable message until a new test passes.', 'niche-clever-interactive');
        } elseif ($failed) {
            $message = __('The last connection test did not pass, so visitors see the unavailable message until a test passes.', 'niche-clever-interactive');
        } else {
            $message = __('This configuration has not passed a connection test yet, so visitors see the unavailable message until one does.', 'niche-clever-interactive');
        }

        echo '<div class="notice notice-warning ncli-admin__retest-notice"><p><strong>'
            . esc_html(__('Connection test needed.', 'niche-clever-interactive')) . '</strong> ' . esc_html($message)
            . '</p><p><a href="' . esc_attr($setupUrl) . '">' . esc_html(__('Go to Setup and press Test connection', 'niche-clever-interactive')) . '</a>'
            . ($failed ? ' &middot; <a href="' . esc_attr($diagnosticsUrl) . '">' . esc_html(__('Open Diagnostics to see why', 'niche-clever-interactive')) . '</a>' : '')
            . '</p></div>';
    }
}
