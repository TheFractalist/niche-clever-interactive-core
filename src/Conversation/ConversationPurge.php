<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

use NicheCleverInteractive\Core\Configuration\CoreSettings;

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
    /** @var callable */
    private $clock;

    public function __construct(ConversationStore $store, ?callable $clock = null)
    {
        $this->store = $store;
        $this->clock = $clock ?? 'time';
    }

    /** @return int Number of turns removed. */
    public function run(CoreSettings $settings): int
    {
        $days = $settings->conversationRetentionDays();
        if ($days <= 0) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', (int) call_user_func($this->clock) - $days * 86400);
        $removed = 0;
        for ($batch = 0; $batch < self::MAX_BATCHES; ++$batch) {
            $count = $this->store->purgeOlderThan($cutoff, self::BATCH);
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
