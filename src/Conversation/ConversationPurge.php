<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;

/**
 * Applies the owner's retention period. It runs daily whether or not logging is
 * currently enabled, so switching logging off never strands old records.
 */
final class ConversationPurge
{
    public const HOOK = 'ncli_core_purge_conversations';
    private const BATCH = 500;
    private const MAX_BATCHES = 10;

    private ConversationStore $store;
    private ?ProfileDirectory $policy;
    /** @var callable */
    private $clock;

    public function __construct(ConversationStore $store, ?callable $clock = null, ?ProfileDirectory $policy = null)
    {
        $this->policy = $policy;
        $this->store = $store;
        $this->clock = $clock ?? 'time';
    }

    /** @return int Number of turns removed. */
    public function run(CoreSettings $settings): int
    {
        $now = (int) call_user_func($this->clock);
        $removed = 0;

        // Profiles an extension gave their own schedule follow it (0 means keep until deleted);
        // every other profile follows Core's schedule. Without an extension, or if it misbehaves,
        // Core's schedule applies to all.
        $overrides = $this->store instanceof ConversationCriteriaStore && $this->policy !== null ? $this->policy->retentionOverrides() : [];
        foreach ($overrides as $profileId => $profileDays) {
            if ($profileDays > 0) {
                $cutoff = gmdate('Y-m-d H:i:s', $now - $profileDays * 86400);
                $removed += $this->batches(fn (): int => $this->store->purgeProfileOlderThan((string) $profileId, $cutoff, self::BATCH));
            }
        }

        $days = $settings->conversationRetentionDays();
        if ($days > 0) {
            $cutoff = gmdate('Y-m-d H:i:s', $now - $days * 86400);
            $removed += $overrides === [] || !$this->store instanceof ConversationCriteriaStore
                ? $this->batches(fn (): int => $this->store->purgeOlderThan($cutoff, self::BATCH))
                : $this->batches(fn (): int => $this->store->purgeOlderThanExcluding(array_map('strval', array_keys($overrides)), $cutoff, self::BATCH));
        }

        return $removed;
    }

    /** @param callable(): int $step */
    private function batches(callable $step): int
    {
        $removed = 0;
        for ($batch = 0; $batch < self::MAX_BATCHES; ++$batch) {
            $count = $step();
            $removed += $count;
            if ($count < self::BATCH) {
                break;
            }
        }

        return $removed;
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action(self::HOOK, function (): void {
            $stored = function_exists('get_option') ? get_option(CoreSettings::OPTION_NAME, CoreSettings::defaults()) : CoreSettings::defaults();
            try {
                $this->run(CoreSettings::fromArray(is_array($stored) ? $stored : CoreSettings::defaults()));
            } catch (\Throwable $exception) {
                // A malformed stored setting or database error leaves records untouched until the next run.
            }
        });
    }

    public static function ensureScheduled(): void
    {
        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && !wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 3600, 'daily', self::HOOK);
        }
    }

    public static function unschedule(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }
    }
}
